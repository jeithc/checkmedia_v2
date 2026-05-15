<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Services\Advisual\AdvisualConnector;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdvisualPurchaseOrderSyncService
{
    protected AdvisualConnector $connector;

    public function __construct(DatabaseManager $database, ?AdvisualConnector $connector = null)
    {
        $this->connector = $connector ?? new AdvisualConnector($database);
    }

    /**
     * Sync purchase orders for recent maintenances with an Advisual requisition.
     */
    public function syncPendingMaintenances(?CarbonInterface $checkedAt = null): array
    {
        $checkedAt = $checkedAt ? Carbon::instance($checkedAt) : now();
        $maintenances = $this->getMaintenancesToSync($checkedAt);

        $summary = [
            'eligible' => $maintenances->count(),
            'processed' => 0,
            'found' => 0,
            'with_value' => 0,
            'updated' => 0,
            'missing' => 0,
            'errors' => 0,
        ];

        foreach ($maintenances as $maintenance) {
            $result = $this->syncMaintenance($maintenance, $checkedAt);

            $summary['processed']++;

            if ($result['status'] === 'missing') {
                $summary['missing']++;
            }

            if ($result['status'] === 'errors') {
                $summary['errors']++;
            }

            if ($result['found']) {
                $summary['found']++;
            }

            if ($result['with_value']) {
                $summary['with_value']++;
            }

            if ($result['updated']) {
                $summary['updated']++;
            }
        }

        return $summary;
    }

    /**
     * Sync a single maintenance against Advisual order data.
     */
    public function syncMaintenance(Maintenance $maintenance, ?CarbonInterface $checkedAt = null): array
    {
        $checkedAt = $checkedAt ? Carbon::instance($checkedAt) : now();

        if (! $maintenance->hasRequisition()) {
            return $this->result('missing');
        }

        try {
            $requisitionStatus = $this->fetchRequisitionStatus($maintenance->advisual_requisition_id);

            if (! $requisitionStatus) {
                $maintenance->update([
                    'advisual_purchase_order_last_checked_at' => $checkedAt,
                    'advisual_purchase_order_sync_error' => 'Requisición no encontrada en Advisual.',
                ]);

                return $this->result('missing');
            }

            if ($this->isRequisitionCancelled($requisitionStatus)) {
                $maintenance->update([
                    'advisual_purchase_order_last_checked_at' => $checkedAt,
                    'advisual_purchase_order_sync_error' => 'Requisición anulada en Advisual.',
                ]);

                return $this->result('missing');
            }

            $purchaseOrders = $this->fetchPurchaseOrders($maintenance->advisual_requisition_id);

            if (empty($purchaseOrders)) {
                $maintenance->update([
                    'advisual_purchase_order_last_checked_at' => $checkedAt,
                    'advisual_purchase_order_sync_error' => null,
                ]);

                return $this->result('missing');
            }

            $payload = $this->buildPayload($purchaseOrders, $checkedAt);
            $hasChanges = $this->hasChanges($maintenance, $payload);

            $maintenance->fill($payload);
            $maintenance->save();

            Log::info('Advisual purchase order synchronized', [
                'maintenance_id' => $maintenance->id,
                'requisition_id' => $maintenance->advisual_requisition_id,
                'purchase_order_id' => $maintenance->advisual_purchase_order_id,
                'line_id' => $maintenance->advisual_purchase_order_line_id,
                'updated' => $hasChanges,
            ]);

            return $this->result('synced', found: true, withValue: $payload['advisual_purchase_order_total'] !== null, updated: $hasChanges);
        } catch (\Throwable $exception) {
            $maintenance->update([
                'advisual_purchase_order_last_checked_at' => $checkedAt,
                'advisual_purchase_order_sync_error' => $exception->getMessage(),
            ]);

            Log::error('Advisual purchase order sync failed', [
                'maintenance_id' => $maintenance->id,
                'requisition_id' => $maintenance->advisual_requisition_id,
                'error' => $exception->getMessage(),
            ]);

            return $this->result('errors');
        }
    }

    protected function getMaintenancesToSync(CarbonInterface $checkedAt): Collection
    {
        $cutoff = $checkedAt->copy()->subMonths($this->getLookbackMonths())->startOfDay();

        return Maintenance::query()
            ->whereNotNull('advisual_requisition_id')
            ->where(function ($query) use ($cutoff) {
                $query->where('created_at', '>=', $cutoff)
                    ->orWhere('requested_at', '>=', $cutoff)
                    ->orWhere('advisual_synced_at', '>=', $cutoff);
            })
            ->where(function ($query) use ($checkedAt) {
                $query->whereNull('advisual_purchase_order_last_checked_at')
                    ->orWhereDate('advisual_purchase_order_last_checked_at', '<', $checkedAt->toDateString());
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * The reliable link is OrdenCompraReqCodigo = Requisicion.id.
     * OrdenCodigo is the order header id and OrdenCompraCodigo is the order line id.
     */
    protected function fetchPurchaseOrders(int $requisitionId): array
    {
        return $this->connector
            ->select(
                'SELECT
                    oc.OrdenCodigo,
                    oc.OrdenCompraCodigo,
                    oc.OrdenCompraReqCodigo,
                    oc.OrdenCompraReqDetCodigo,
                    oc.OrdenCompraDescripcion,
                    oc.OrdenCompraCantidad,
                    oc.OrdenCompraValorUnitario,
                    oc.OrdenCompraFechaCompromiso,
                    oc.OrdenCompraFechaEjecucion,
                    oc.OrdenCompraCreaFecha,
                    oc.OrdenCompraValorCertificado,
                    o.OrdenEstado,
                    o.OrdenAnulaFecha,
                    o.OrdenAnulaUsuario
                FROM OrdenCompra oc
                INNER JOIN Orden o ON o.OrdenCodigo = oc.OrdenCodigo
                WHERE oc.OrdenCompraReqCodigo = ?
                  AND ISNULL(oc.OrdenCompraItemDel, 0) = 0
                  AND ISNULL(o.OrdenEstado, 1) <> 2
                ORDER BY
                    CASE
                        WHEN ISNULL(oc.OrdenCompraValorCertificado, 0) > 0 OR ISNULL(oc.OrdenCompraValorUnitario, 0) > 0 THEN 0
                        ELSE 1
                    END,
                    oc.OrdenCompraCreaFecha DESC,
                    oc.OrdenCodigo DESC,
                    oc.OrdenCompraCodigo DESC',
                [$requisitionId]
            );
    }

    protected function fetchRequisitionStatus(int $requisitionId): ?object
    {
        return $this->connector->selectOne(
            'SELECT TOP 1
                RequisicionCodigo,
                RequisicionEstado,
                RequisicionAnulacionFecha,
                RequisicionAnulacionUsuario
            FROM Requisicion
            WHERE RequisicionCodigo = ?',
            [$requisitionId]
        );
    }

    protected function isRequisitionCancelled(object $status): bool
    {
        $user = trim((string) ($status->RequisicionAnulacionUsuario ?? ''));

        if ($user !== '') {
            return true;
        }

        $date = $status->RequisicionAnulacionFecha ?? null;

        if (! $date) {
            return false;
        }

        try {
            return Carbon::parse($date)->year > 1900;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function buildPayload(array $purchaseOrders, CarbonInterface $checkedAt): array
    {
        $primary = $purchaseOrders[0];
        $aggregate = $this->aggregateTotals($purchaseOrders);

        return [
            'advisual_purchase_order_id' => $primary->OrdenCodigo ?? null,
            'advisual_purchase_order_line_id' => $primary->OrdenCompraCodigo ?? null,
            'advisual_purchase_order_description' => $primary->OrdenCompraDescripcion ?? null,
            'advisual_purchase_order_quantity' => $aggregate['quantity'],
            'advisual_purchase_order_unit_price' => $this->normalizeDecimal($primary->OrdenCompraValorUnitario ?? null, 2),
            'advisual_purchase_order_total' => $aggregate['total'],
            'advisual_purchase_order_created_at' => $this->normalizeDate($primary->OrdenCompraCreaFecha ?? null),
            'advisual_purchase_order_committed_at' => $this->normalizeDate($primary->OrdenCompraFechaCompromiso ?? null),
            'advisual_purchase_order_executed_at' => $this->normalizeDate($primary->OrdenCompraFechaEjecucion ?? null),
            'advisual_purchase_order_last_checked_at' => $checkedAt->format('Y-m-d H:i:s'),
            'advisual_purchase_order_sync_error' => null,
            'final_cost' => $aggregate['total'],
        ];
    }

    /**
     * Aggregate totals across every active OC line tied to the requisition.
     * Prefer ValorCertificado per line; fall back to Cantidad * ValorUnitario when missing.
     */
    protected function aggregateTotals(array $purchaseOrders): array
    {
        $quantityTotal = 0.0;
        $valueTotal = 0.0;
        $anyValue = false;

        foreach ($purchaseOrders as $oc) {
            $quantity = (float) ($oc->OrdenCompraCantidad ?? 0);
            $unitPrice = (float) ($oc->OrdenCompraValorUnitario ?? 0);
            $certifiedRaw = $oc->OrdenCompraValorCertificado ?? null;
            $certified = $certifiedRaw === null || $certifiedRaw === '' ? null : (float) $certifiedRaw;

            $quantityTotal += $quantity;

            if ($certified !== null && $certified > 0) {
                $valueTotal += $certified;
                $anyValue = true;
            } elseif ($quantity > 0 && $unitPrice > 0) {
                $valueTotal += $quantity * $unitPrice;
                $anyValue = true;
            }
        }

        return [
            'quantity' => $quantityTotal > 0 ? number_format($quantityTotal, 4, '.', '') : null,
            'total' => $anyValue ? number_format(round($valueTotal, 2), 2, '.', '') : null,
        ];
    }

    protected function hasChanges(Maintenance $maintenance, array $payload): bool
    {
        foreach ([
            'advisual_purchase_order_id',
            'advisual_purchase_order_line_id',
            'advisual_purchase_order_description',
            'advisual_purchase_order_quantity',
            'advisual_purchase_order_unit_price',
            'advisual_purchase_order_total',
            'advisual_purchase_order_created_at',
            'advisual_purchase_order_committed_at',
            'advisual_purchase_order_executed_at',
            'final_cost',
        ] as $field) {
            if ($this->normalizeComparableValue($maintenance->{$field}) !== $this->normalizeComparableValue($payload[$field])) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeDecimal(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $scale, '.', '');
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    protected function normalizeComparableValue(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function getLookbackMonths(): int
    {
        return max((int) config('services.advisual.purchase_order_lookback_months', 6), 1);
    }

    protected function result(string $status, bool $found = false, bool $withValue = false, bool $updated = false): array
    {
        return [
            'status' => $status,
            'found' => $found,
            'with_value' => $withValue,
            'updated' => $updated,
        ];
    }
}
