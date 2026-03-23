<?php

namespace App\Services;

use App\Models\Maintenance;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdvisualPurchaseOrderSyncService
{
    public function __construct(
        protected DatabaseManager $database
    ) {}

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
            $purchaseOrder = $this->fetchPurchaseOrder($maintenance->advisual_requisition_id);

            if (! $purchaseOrder) {
                $maintenance->update([
                    'advisual_purchase_order_last_checked_at' => $checkedAt,
                    'advisual_purchase_order_sync_error' => null,
                ]);

                return $this->result('missing');
            }

            $payload = $this->buildPayload($purchaseOrder, $checkedAt);
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
    protected function fetchPurchaseOrder(int $requisitionId): ?object
    {
        $rows = $this->database
            ->connection('advisual')
            ->select(
                'SELECT TOP 1
                    OrdenCodigo,
                    OrdenCompraCodigo,
                    OrdenCompraReqCodigo,
                    OrdenCompraReqDetCodigo,
                    OrdenCompraDescripcion,
                    OrdenCompraCantidad,
                    OrdenCompraValorUnitario,
                    OrdenCompraFechaCompromiso,
                    OrdenCompraFechaEjecucion,
                    OrdenCompraCreaFecha,
                    OrdenCompraValorCertificado
                FROM OrdenCompra
                WHERE OrdenCompraReqCodigo = ?
                  AND ISNULL(OrdenCompraItemDel, 0) = 0
                ORDER BY
                    CASE
                        WHEN ISNULL(OrdenCompraValorCertificado, 0) > 0 OR ISNULL(OrdenCompraValorUnitario, 0) > 0 THEN 0
                        ELSE 1
                    END,
                    OrdenCompraCreaFecha DESC,
                    OrdenCodigo DESC,
                    OrdenCompraCodigo DESC',
                [$requisitionId]
            );

        return $rows[0] ?? null;
    }

    protected function buildPayload(object $purchaseOrder, CarbonInterface $checkedAt): array
    {
        $quantity = $this->normalizeDecimal($purchaseOrder->OrdenCompraCantidad ?? null, 4);
        $unitPrice = $this->normalizeDecimal($purchaseOrder->OrdenCompraValorUnitario ?? null, 2);
        $certifiedValue = $this->normalizeDecimal($purchaseOrder->OrdenCompraValorCertificado ?? null, 2);
        $total = $this->resolveTotal($quantity, $unitPrice, $certifiedValue);

        return [
            'advisual_purchase_order_id' => $purchaseOrder->OrdenCodigo ?? null,
            'advisual_purchase_order_line_id' => $purchaseOrder->OrdenCompraCodigo ?? null,
            'advisual_purchase_order_description' => $purchaseOrder->OrdenCompraDescripcion ?? null,
            'advisual_purchase_order_quantity' => $quantity,
            'advisual_purchase_order_unit_price' => $unitPrice,
            'advisual_purchase_order_total' => $total,
            'advisual_purchase_order_created_at' => $this->normalizeDate($purchaseOrder->OrdenCompraCreaFecha ?? null),
            'advisual_purchase_order_committed_at' => $this->normalizeDate($purchaseOrder->OrdenCompraFechaCompromiso ?? null),
            'advisual_purchase_order_executed_at' => $this->normalizeDate($purchaseOrder->OrdenCompraFechaEjecucion ?? null),
            'advisual_purchase_order_last_checked_at' => $checkedAt->format('Y-m-d H:i:s'),
            'advisual_purchase_order_sync_error' => null,
            'final_cost' => $total,
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

    protected function resolveTotal(?string $quantity, ?string $unitPrice, ?string $certifiedValue): ?string
    {
        if ($certifiedValue !== null) {
            return $certifiedValue;
        }

        if ($quantity === null || $unitPrice === null) {
            return null;
        }

        return number_format(round(((float) $quantity) * ((float) $unitPrice), 2), 2, '.', '');
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
