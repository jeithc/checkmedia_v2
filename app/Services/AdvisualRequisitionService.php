<?php

namespace App\Services;

use App\Models\Maintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisualRequisitionService
{
    /**
     * Create a requisition in Advisual (SQL Server) for the given maintenance.
     *
     * TODO: Jeith must provide the exact INSERT statement with table and field names.
     * Currently this is a placeholder that simulates the integration.
     */
    public function createRequisition(Maintenance $maintenance): bool
    {
        try {
            $space = $maintenance->advertisingSpace;

            if (!$space) {
                $this->markError($maintenance, 'No se encontró el espacio publicitario asociado.');
                return false;
            }

            // TODO: Replace with actual Advisual INSERT when SQL is provided by Jeith
            // Example of what the integration will look like:
            //
            // $requisitionId = DB::connection('advisual')->table('requisiciones')->insertGetId([
            //     'espacio_codigo' => $space->external_code,
            //     'categoria' => $maintenance->category,
            //     'descripcion' => $maintenance->description,
            //     'prioridad' => $maintenance->priority,
            //     'solicitado_por' => $maintenance->requestedBy?->name ?? 'Sistema',
            //     'fecha_solicitud' => now(),
            // ]);

            // Placeholder: Generate a temporary ID
            $requisitionId = 'REQ-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $maintenance->update([
                'advisual_requisition_id' => $requisitionId,
                'advisual_synced_at' => now(),
                'advisual_sync_error' => null,
                'status' => Maintenance::STATUS_IN_PROGRESS,
            ]);

            Log::info("Advisual requisition created (placeholder)", [
                'maintenance_id' => $maintenance->id,
                'requisition_id' => $requisitionId,
                'space_code' => $space->external_code,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->markError($maintenance, $e->getMessage());
            return false;
        }
    }

    protected function markError(Maintenance $maintenance, string $error): void
    {
        $maintenance->update([
            'advisual_sync_error' => $error,
            'status' => Maintenance::STATUS_PENDING_ADVISUAL,
        ]);

        Log::error("Advisual requisition failed", [
            'maintenance_id' => $maintenance->id,
            'error' => $error,
        ]);
    }
}
