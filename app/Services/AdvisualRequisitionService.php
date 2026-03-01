<?php

namespace App\Services;

use App\Models\Maintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisualRequisitionService
{
    /**
     * Create a requisition in Advisual (SQL Server) for the given maintenance.
     */
    public function createRequisition(Maintenance $maintenance): bool
    {
        try {
            $solicitanteUuid = config('services.advisual.solicitante_uuid');

            if (!$solicitanteUuid) {
                $this->markError($maintenance, 'ADVISUAL_SOLICITANTE_UUID no está configurado en .env');
                return false;
            }

            $space = $maintenance->advertisingSpace;

            if (!$space) {
                $this->markError($maintenance, 'No se encontró el espacio publicitario asociado.');
                return false;
            }

            $now = now();
            $creaUsuario = config('services.advisual.crea_usuario', 'CheckMedia');
            $estado = config('services.advisual.requisicion_estado', 1);
            $tipo = config('services.advisual.requisicion_tipo', 2);
            $serialProd = config('services.advisual.serial_prod', 1);
            $serialAdmin = config('services.advisual.serial_admin', 0);

            $observacion = strtoupper($maintenance->category ?? 'GENERAL')
                . ' | ' . ($space->external_code ?? 'SIN-CODIGO')
                . ' - ' . ($maintenance->description ?? '');

            $requisitionId = DB::connection('advisual')->selectOne("
                SET NOCOUNT ON;
                INSERT INTO Requisicion (
                    RequisicionFecha,
                    RequisicionSolicitanteCodigo,
                    RequisicionTipo,
                    RequisicionObservacion,
                    RequisicionEstado,
                    RequisicionSerialAdmin,
                    RequisicionSerialProd,
                    RequisicionCreaUsuario,
                    RequisicionCreaFecha,
                    RequisicionModificaUsuario,
                    RequisicionModificaFecha,
                    RequisicionFechaSugerida
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                SELECT SCOPE_IDENTITY() AS id;
            ", [
                $now,
                $solicitanteUuid,
                $tipo,
                $observacion,
                $estado,
                $serialAdmin,
                $serialProd,
                $creaUsuario,
                $now,
                $creaUsuario,
                $now,
                $now,
            ]);

            if (!$requisitionId || !$requisitionId->id) {
                $this->markError($maintenance, 'No se obtuvo el ID de la requisición insertada en Advisual.');
                return false;
            }

            $maintenance->update([
                'advisual_requisition_id' => $requisitionId->id,
                'advisual_synced_at' => $now,
                'advisual_sync_error' => null,
                'status' => Maintenance::STATUS_IN_PROGRESS,
            ]);

            Log::info("Advisual requisition created", [
                'maintenance_id' => $maintenance->id,
                'requisition_id' => $requisitionId->id,
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
