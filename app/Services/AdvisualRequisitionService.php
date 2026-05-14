<?php

namespace App\Services;

use App\Models\Maintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisualRequisitionService
{
    private ?int $cachedUnidadCodigo = null;

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

            // Observación incluye código de espacio + criterios linkeados + descripción
            $categoryLabel = $maintenance->auditValues()
                ->with('criterion')
                ->get()
                ->pluck('criterion.name')
                ->filter()
                ->map(fn ($c) => strtoupper($c))
                ->unique()
                ->values()
                ->join(', ');

            if ($categoryLabel === '') {
                $categoryLabel = strtoupper($maintenance->category ?? 'GENERAL');
            }

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

            $reqId = (int) $requisitionId->id;

            try {
                $this->insertRequisitionProductiva($reqId, $maintenance, $categoryLabel);
            } catch (\Exception $eDetail) {
                try {
                    $this->deleteRequisicion($reqId);
                } catch (\Exception $eDel) {
                    Log::error('Failed to rollback Requisicion after detail failure', [
                        'requisicion_id' => $reqId,
                        'error' => $eDel->getMessage(),
                    ]);
                }
                $this->markError($maintenance, 'Falló inserción de RequisicionProductiva: ' . $eDetail->getMessage());
                return false;
            }

            $maintenance->update([
                'advisual_requisition_id' => $reqId,
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

    /**
     * Insert one detail line in dbo.RequisicionProductiva for the just-created Requisicion.
     */
    private function insertRequisitionProductiva(int $requisicionCodigo, Maintenance $maintenance, string $categoryLabel): void
    {
        $space = $maintenance->advertisingSpace;
        $externalCode = $space?->external_code;

        if (!$externalCode) {
            throw new \RuntimeException('AdvertisingSpace external_code is missing.');
        }

        $row = $this->selectAdvisualOne(
            'SELECT TOP 1 e.EspacioLocacionCodigo, l.ProductoCodigo
             FROM Espacio e
             INNER JOIN Locacion l ON l.LocacionCodigo = e.EspacioLocacionCodigo
             WHERE e.EspacioCodigo = ?',
            [$externalCode]
        );

        if (!$row) {
            throw new \RuntimeException("No se encontró Espacio {$externalCode} en Advisual.");
        }

        $locacionCodigo = (int) $row->EspacioLocacionCodigo;
        $productoCodigo = (int) $row->ProductoCodigo;
        $unidadCodigo = $this->resolveDefaultUnidadCodigo();

        $description = $categoryLabel;
        if (!empty($maintenance->description)) {
            $description .= ' - ' . $maintenance->description;
        }
        $description = mb_substr($description, 0, 8000);

        $cantidad = (float) config('services.advisual.requiprod_cantidad', 1);
        $canPedida = (float) config('services.advisual.requiprod_can_pedida', 0);

        $sql = '
            INSERT INTO RequisicionProductiva (
                RequisicionCodigo,
                RequiProdCodigo,
                RequiProdEspacioLocacion,
                RequiProdEspacioCodigo,
                RequiProdProductoCodigo,
                RequiProdLocacionCodigo,
                RequiProdDescripcion,
                RequiProdCantidad,
                RequiProdUnidadCodigo,
                RequiProdCanPedida
            ) VALUES (?, 1, 1, ?, ?, ?, ?, ?, ?, ?);
        ';

        $bindings = [
            $requisicionCodigo,
            $externalCode,
            $productoCodigo,
            $locacionCodigo,
            $description,
            $cantidad,
            $unidadCodigo,
            $canPedida,
        ];

        $this->executeAdvisualWrite($sql, $bindings);

        Log::info('Advisual RequisicionProductiva inserted', [
            'requisicion_id' => $requisicionCodigo,
            'maintenance_id' => $maintenance->id,
            'espacio_codigo' => $externalCode,
            'producto_codigo' => $productoCodigo,
            'locacion_codigo' => $locacionCodigo,
            'unidad_codigo' => $unidadCodigo,
        ]);
    }

    /**
     * Resolve the Advisual default UnidadCodigo (Unidadmedida.UnidadDefault = '1').
     * Memoised on the instance; falls back to config when the lookup fails.
     */
    private function resolveDefaultUnidadCodigo(): int
    {
        if ($this->cachedUnidadCodigo !== null) {
            return $this->cachedUnidadCodigo;
        }

        try {
            $row = $this->selectAdvisualOne(
                "SELECT TOP 1 UnidadCodigo FROM Unidadmedida WHERE UnidadDefault = '1'"
            );
            if ($row && isset($row->UnidadCodigo)) {
                return $this->cachedUnidadCodigo = (int) $row->UnidadCodigo;
            }
        } catch (\Exception $e) {
            Log::warning('Advisual UnidadDefault lookup failed; using fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->cachedUnidadCodigo = (int) config('services.advisual.requiprod_unidad_fallback', 13);
    }

    /**
     * Best-effort rollback of a Requisicion when the detail insert fails.
     */
    private function deleteRequisicion(int $requisicionCodigo): void
    {
        $this->executeAdvisualWrite(
            'DELETE FROM Requisicion WHERE RequisicionCodigo = ?;',
            [$requisicionCodigo]
        );
    }

    /**
     * Run a write statement on Advisual using FreeTDS ODBC first, native sqlsrv as fallback.
     * Mirrors the dual-path strategy used by the parent Requisicion insert above.
     */
    private function executeAdvisualWrite(string $sql, array $bindings): void
    {
        try {
            $username = config('database.connections.advisual.username');
            $password = config('database.connections.advisual.password');
            $database = config('database.connections.advisual.database');
            $host = config('database.connections.advisual.host');
            $port = config('database.connections.advisual.port', '1433');

            $dsn = "odbc:Driver=FreeTDS;Server={$host};Port={$port};Database={$database};TDS_Version=7.4;";
            $pdo = new \PDO($dsn, $username, $password);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
        } catch (\Exception $eOdbc) {
            try {
                DB::connection('advisual')->statement($sql, $bindings);
            } catch (\Exception $eNative) {
                throw new \Exception('ODBC Error: ' . $eOdbc->getMessage() . ' | Native Error: ' . $eNative->getMessage());
            }
        }
    }

    /**
     * Run a SELECT on Advisual using FreeTDS ODBC first, native sqlsrv as fallback.
     */
    private function selectAdvisualOne(string $sql, array $bindings = [])
    {
        try {
            $username = config('database.connections.advisual.username');
            $password = config('database.connections.advisual.password');
            $database = config('database.connections.advisual.database');
            $host = config('database.connections.advisual.host');
            $port = config('database.connections.advisual.port', '1433');

            $dsn = "odbc:Driver=FreeTDS;Server={$host};Port={$port};Database={$database};TDS_Version=7.4;";
            $pdo = new \PDO($dsn, $username, $password);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);
            return $row ?: null;
        } catch (\Exception $eOdbc) {
            try {
                return DB::connection('advisual')->selectOne($sql, $bindings);
            } catch (\Exception $eNative) {
                throw new \Exception('ODBC Error: ' . $eOdbc->getMessage() . ' | Native Error: ' . $eNative->getMessage());
            }
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
