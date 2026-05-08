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
            // TODO: cambiar por el del user login ($maintenance->requestedBy->uuid)
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
            // RequisicionCreaUsuario mapeado al username (UsuarioLogin)
            $creaUsuario = $maintenance->requestedBy ? $maintenance->requestedBy->username : config('services.advisual.crea_usuario', 'CheckMedia');
            $estado = config('services.advisual.requisicion_estado', 1);
            $tipo = config('services.advisual.requisicion_tipo', 2);
            $serialProd = config('services.advisual.serial_prod', 1);
            $serialAdmin = config('services.advisual.serial_admin', 0);

            // Observación incluye código de espacio + categoría + descripción del problema
            $categoryLabel = strtoupper($maintenance->category ?? 'GENERAL');
            $observacion = $space->external_code . ' - ' . $categoryLabel . ' - ' . ($maintenance->description ?: 'Sin observaciones');

            $sqlQuery = "
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
            ";

            $nowStr = $now->format('Y-m-d H:i:s');

            $bindings = [
                $nowStr,
                $solicitanteUuid,
                $tipo,
                $observacion,
                $estado,
                $serialAdmin,
                $serialProd,
                $creaUsuario,
                $nowStr,
                $creaUsuario,
                $nowStr,
                $nowStr,
            ];

            $requisitionId = null;

            try {
                // 1. Intentar FreeTDS ODBC (Prioridad para Hostinger Shared)
                $username = config('database.connections.advisual.username');
                $password = config('database.connections.advisual.password');
                $database = config('database.connections.advisual.database');
                $host = config('database.connections.advisual.host');
                $port = config('database.connections.advisual.port', '1433');

                $dsn = "odbc:Driver=FreeTDS;Server={$host};Port={$port};Database={$database};TDS_Version=7.4;";
                $pdo = new \PDO($dsn, $username, $password);
                $stmt = $pdo->prepare($sqlQuery);
                $stmt->execute($bindings);
                
                do {
                    $row = $stmt->fetch(\PDO::FETCH_OBJ);
                    if ($row && isset($row->id)) {
                        $requisitionId = $row;
                        break;
                    }
                } while ($stmt->nextRowset());

                // Fallback preventivo si fetch directo falló pero insertó (FreeTDS quirk)
                if (!$requisitionId) {
                    $stmt = $pdo->query("SELECT @@IDENTITY AS id");
                    $requisitionId = $stmt->fetch(\PDO::FETCH_OBJ);
                }
            } catch (\Exception $eOdbc) {
                // 2. Fallback: Intentar conexión estándar nativa (Local/VPS con sqlsrv)
                try {
                    $requisitionId = DB::connection('advisual')->selectOne($sqlQuery, $bindings);
                } catch (\Exception $eNative) {
                    throw new \Exception("ODBC Error: " . $eOdbc->getMessage() . " | Native Error: " . $eNative->getMessage());
                }
            }

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
