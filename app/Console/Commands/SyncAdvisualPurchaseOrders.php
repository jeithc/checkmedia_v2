<?php

namespace App\Console\Commands;

use App\Services\AdvisualPurchaseOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAdvisualPurchaseOrders extends Command
{
    protected $signature = 'checkmedia:sync-purchase-orders {--date=}'; // pragma: allowlist secret

    protected $description = 'Sincroniza diariamente las órdenes de compra de Advisual asociadas a requisiciones locales.';

    public function handle(AdvisualPurchaseOrderSyncService $service): int
    {
        $referenceDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $this->info('Iniciando sincronización diaria de órdenes de compra...');

        $summary = $service->syncPendingMaintenances($referenceDate);

        if ($summary['eligible'] === 0) {
            $this->info('No hay mantenimientos con RQ pendientes por revisar en la ventana configurada.');

            return self::SUCCESS;
        }

        $this->info(
            'Revisión completada. '.
            "Procesados: {$summary['processed']}. ".
            "OC encontradas: {$summary['found']}. ".
            "Con valor: {$summary['with_value']}. ".
            "Actualizados: {$summary['updated']}. ".
            "Sin OC: {$summary['missing']}. ".
            "Errores: {$summary['errors']}."
        );

        return self::SUCCESS;
    }
}
