<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use App\Services\Advisual\AdvisualConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisualRequisitionService
{
    private ?int $cachedUnidadCodigo = null;

    protected AdvisualConnector $connector;

    public function __construct(?AdvisualConnector $connector = null)
    {
        $this->connector = $connector ?? new AdvisualConnector;
    }

    /**
     * @var array<int, object>|null Memoised Advisual Usuarios rows.
     */
    private ?array $cachedUsuarios = null;

    /**
     * Fetch Advisual Usuarios rows (memoised per instance). Returns [] on error.
     *
     * @return array<int, object>
     */
    private function fetchUsuarios(): array
    {
        if ($this->cachedUsuarios !== null) {
            return $this->cachedUsuarios;
        }

        try {
            return $this->cachedUsuarios = $this->connector->select(
                'SELECT UsuarioGUID, UsuarioNombreCompleto, UsuarioLogin, UsuarioEmail
                 FROM Usuarios
                 ORDER BY UsuarioNombreCompleto'
            );
        } catch (\Throwable $e) {
            Log::warning('Advisual listUsuarios failed', ['error' => $e->getMessage()]);

            return $this->cachedUsuarios = [];
        }
    }

    /**
     * List Advisual users for the solicitante dropdown.
     *
     * @return array<string, string> [UsuarioGUID => "NombreCompleto (login)"]
     */
    public function listUsuarios(): array
    {
        $options = [];
        foreach ($this->fetchUsuarios() as $row) {
            if (empty($row->UsuarioGUID)) {
                continue;
            }
            $name = trim($row->UsuarioNombreCompleto ?? '') ?: ($row->UsuarioEmail ?? $row->UsuarioLogin ?? $row->UsuarioGUID);
            $login = trim($row->UsuarioLogin ?? '');
            $options[$row->UsuarioGUID] = $login ? "{$name} ({$login})" : $name;
        }

        return $options;
    }

    /**
     * Suggest an Advisual UsuarioGUID by matching email (case-insensitive).
     */
    public function suggestGuidForEmail(?string $email): ?string
    {
        $email = trim(strtolower($email ?? ''));
        if ($email === '') {
            return null;
        }

        foreach ($this->fetchUsuarios() as $row) {
            if (! empty($row->UsuarioGUID) && trim(strtolower($row->UsuarioEmail ?? '')) === $email) {
                return $row->UsuarioGUID;
            }
        }

        return null;
    }

    /**
     * Create a requisition in Advisual (SQL Server) for the given maintenance.
     */
    public function createRequisition(Maintenance $maintenance): bool
    {
        try {
            $solicitanteUuid = $maintenance->requestedBy?->advisual_usuario_guid;

            if (! $solicitanteUuid) {
                $this->markError($maintenance, 'El usuario solicitante no tiene un usuario de Advisual asignado.');

                return false;
            }

            $space = $maintenance->advertisingSpace;

            if (! $space) {
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
            $criterionLabels = $maintenance->auditValues()
                ->with('criterion')
                ->get()
                ->pluck('criterion.name')
                ->filter()
                ->map(fn ($c) => strtoupper($c))
                ->unique()
                ->values()
                ->all();

            if (empty($criterionLabels)) {
                $criterionLabels = [strtoupper($maintenance->category ?? 'GENERAL')];
            }

            $categoryLabel = implode(', ', $criterionLabels);

            $observacion = $space->external_code.' - '.$categoryLabel.' - '.($maintenance->description ?: 'Sin observaciones');

            $reqId = $this->insertRequisicionHeader(
                $solicitanteUuid,
                $creaUsuario,
                $observacion,
                $now->format('Y-m-d H:i:s'),
                $tipo,
                $estado,
                $serialAdmin,
                $serialProd
            );

            if (! $reqId) {
                $this->markError($maintenance, 'No se obtuvo el ID de la requisición insertada en Advisual.');

                return false;
            }

            try {
                $this->insertRequisitionProductiva($reqId, $maintenance, $criterionLabels);
            } catch (\Exception $eDetail) {
                try {
                    $this->deleteRequisicion($reqId);
                } catch (\Exception $eDel) {
                    Log::error('Failed to rollback Requisicion after detail failure', [
                        'requisicion_id' => $reqId,
                        'error' => $eDel->getMessage(),
                    ]);
                }
                $this->markError($maintenance, 'Falló inserción de RequisicionProductiva: '.$eDetail->getMessage());

                return false;
            }

            $maintenance->update([
                'advisual_requisition_id' => $reqId,
                'advisual_synced_at' => $now,
                'advisual_sync_error' => null,
                'status' => Maintenance::STATUS_IN_PROGRESS,
            ]);

            Log::info('Advisual requisition created', [
                'maintenance_id' => $maintenance->id,
                'requisition_id' => $reqId,
                'space_code' => $space->external_code,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->markError($maintenance, $e->getMessage());

            return false;
        }
    }

    /**
     * Create a single Advisual requisition for a whole batch: one Requisicion header
     * plus one RequisicionProductiva line per maintenance (space) in the batch.
     *
     * Each line uses the maintenance's own `advisual_requisition_line` as RequiProdCodigo,
     * so the purchase-order sync can later filter costs by OrdenCompraReqDetCodigo.
     */
    public function createBatchRequisition(RequisitionBatch $batch): bool
    {
        $maintenances = $batch->maintenances()
            ->with('advertisingSpace')
            ->orderBy('advisual_requisition_line')
            ->get();

        if ($maintenances->isEmpty()) {
            $this->markBatchError($batch, 'El lote no tiene mantenimientos para enviar a Advisual.');

            return false;
        }

        $solicitanteUuid = $batch->createdBy?->advisual_usuario_guid;

        if (! $solicitanteUuid) {
            $this->failBatch($batch, $maintenances, 'El usuario solicitante no tiene un usuario de Advisual asignado.');

            return false;
        }

        $reqId = null;

        try {
            $now = now();
            // RequisicionCreaUsuario mapeado al username (UsuarioLogin)
            $creaUsuario = $batch->createdBy ? $batch->createdBy->username : config('services.advisual.crea_usuario', 'CheckMedia');

            $observacion = 'LOTE PREVENTIVO - '.$batch->name
                .($batch->city ? ' - '.strtoupper($batch->city) : '')
                .' - '.$maintenances->count().' espacios';
            $observacion = mb_substr($observacion, 0, 8000);

            $reqId = $this->insertRequisicionHeader(
                $solicitanteUuid,
                $creaUsuario,
                $observacion,
                $now->format('Y-m-d H:i:s'),
                config('services.advisual.requisicion_tipo', 2),
                config('services.advisual.requisicion_estado', 1),
                config('services.advisual.serial_admin', 0),
                config('services.advisual.serial_prod', 1)
            );

            if (! $reqId) {
                $this->failBatch($batch, $maintenances, 'No se obtuvo el ID de la requisición insertada en Advisual.');

                return false;
            }

            foreach ($maintenances as $maintenance) {
                $this->insertBatchRequisitionProductiva($reqId, $maintenance);
            }

            $batch->update([
                'advisual_requisition_id' => $reqId,
                'advisual_synced_at' => $now,
                'advisual_sync_error' => null,
            ]);

            foreach ($maintenances as $maintenance) {
                $maintenance->update([
                    'advisual_requisition_id' => $reqId,
                    'advisual_synced_at' => $now,
                    'advisual_sync_error' => null,
                    'status' => Maintenance::STATUS_IN_PROGRESS,
                ]);
            }

            Log::info('Advisual batch requisition created', [
                'batch_id' => $batch->id,
                'requisition_id' => $reqId,
                'lines' => $maintenances->count(),
            ]);

            return true;
        } catch (\Exception $e) {
            if ($reqId) {
                try {
                    $this->deleteRequisicion($reqId);
                } catch (\Exception $eDel) {
                    Log::error('Failed to rollback batch Requisicion after failure', [
                        'requisicion_id' => $reqId,
                        'error' => $eDel->getMessage(),
                    ]);
                }
            }

            $this->failBatch($batch, $maintenances, $e->getMessage());

            return false;
        }
    }

    /**
     * Insert the RequisicionProductiva line that belongs to a batch maintenance.
     */
    private function insertBatchRequisitionProductiva(int $requisicionCodigo, Maintenance $maintenance): void
    {
        $externalCode = $maintenance->advertisingSpace?->external_code;

        if (! $externalCode) {
            throw new \RuntimeException("AdvertisingSpace external_code is missing for maintenance {$maintenance->id}.");
        }

        $codigo = (int) $maintenance->advisual_requisition_line;

        if ($codigo < 1) {
            throw new \RuntimeException("El mantenimiento {$maintenance->id} no tiene número de línea de requisición.");
        }

        $row = $this->resolveEspacioRow($externalCode);

        $label = strtoupper($maintenance->category ?? 'GENERAL');
        $description = $label;
        if (! empty($maintenance->description)) {
            $description .= ' - '.$maintenance->description;
        }

        $this->insertRequisicionProductivaRow(
            $requisicionCodigo,
            $codigo,
            $externalCode,
            (int) $row->ProductoCodigo,
            (int) $row->EspacioLocacionCodigo,
            $description
        );

        Log::info('Advisual RequisicionProductiva inserted', [
            'requisicion_id' => $requisicionCodigo,
            'requi_prod_codigo' => $codigo,
            'maintenance_id' => $maintenance->id,
            'espacio_codigo' => $externalCode,
        ]);
    }

    /**
     * Insert one detail line per criterion in dbo.RequisicionProductiva for the just-created Requisicion.
     */
    private function insertRequisitionProductiva(int $requisicionCodigo, Maintenance $maintenance, array $criterionLabels): void
    {
        $space = $maintenance->advertisingSpace;
        $externalCode = $space?->external_code;

        if (! $externalCode) {
            throw new \RuntimeException('AdvertisingSpace external_code is missing.');
        }

        $row = $this->resolveEspacioRow($externalCode);

        $locacionCodigo = (int) $row->EspacioLocacionCodigo;
        $productoCodigo = (int) $row->ProductoCodigo;
        $unidadCodigo = $this->resolveDefaultUnidadCodigo();

        $labels = empty($criterionLabels) ? [strtoupper($maintenance->category ?? 'GENERAL')] : $criterionLabels;

        $codigo = 1;
        foreach ($labels as $label) {
            $description = $label;
            if (! empty($maintenance->description)) {
                $description .= ' - '.$maintenance->description;
            }

            $this->insertRequisicionProductivaRow(
                $requisicionCodigo,
                $codigo,
                $externalCode,
                $productoCodigo,
                $locacionCodigo,
                $description
            );

            Log::info('Advisual RequisicionProductiva inserted', [
                'requisicion_id' => $requisicionCodigo,
                'requi_prod_codigo' => $codigo,
                'criterion_label' => $label,
                'maintenance_id' => $maintenance->id,
                'espacio_codigo' => $externalCode,
                'producto_codigo' => $productoCodigo,
                'locacion_codigo' => $locacionCodigo,
                'unidad_codigo' => $unidadCodigo,
            ]);

            $codigo++;
        }
    }

    /**
     * Resolve the Advisual Espacio/Locacion row for an advertising space external_code.
     *
     * external_code is a STRING in Advisual (2, 3 and 5 digit codes coexist) — never cast it.
     *
     * @throws \RuntimeException when the space does not exist in Advisual
     */
    private function resolveEspacioRow(string $externalCode): object
    {
        $row = $this->selectAdvisualOne(
            'SELECT TOP 1 e.EspacioLocacionCodigo, l.ProductoCodigo
             FROM Espacio e
             INNER JOIN Locacion l ON l.LocacionCodigo = e.EspacioLocacionCodigo
             WHERE e.EspacioCodigo = ?',
            [$externalCode]
        );

        if (! $row) {
            throw new \RuntimeException("No se encontró Espacio {$externalCode} en Advisual.");
        }

        return $row;
    }

    /**
     * Insert a single dbo.RequisicionProductiva row.
     */
    private function insertRequisicionProductivaRow(
        int $requisicionCodigo,
        int $codigo,
        string $externalCode,
        int $productoCodigo,
        int $locacionCodigo,
        string $description
    ): void {
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
            ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?);
        ';

        $this->executeAdvisualWrite($sql, [
            $requisicionCodigo,
            $codigo,
            $externalCode,
            $productoCodigo,
            $locacionCodigo,
            mb_substr($description, 0, 8000),
            (float) config('services.advisual.requiprod_cantidad', 1),
            $this->resolveDefaultUnidadCodigo(),
            (float) config('services.advisual.requiprod_can_pedida', 0),
        ]);
    }

    /**
     * Insert the dbo.Requisicion header and return the new RequisicionCodigo.
     *
     * Tries FreeTDS ODBC first (Hostinger shared hosting), falls back to the native
     * `advisual` connection.
     */
    private function insertRequisicionHeader(
        string $solicitanteUuid,
        ?string $creaUsuario,
        string $observacion,
        string $nowStr,
        $tipo,
        $estado,
        $serialAdmin,
        $serialProd
    ): ?int {
        $sqlQuery = '
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
        ';

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
            if (! $requisitionId) {
                $stmt = $pdo->query('SELECT @@IDENTITY AS id');
                $requisitionId = $stmt->fetch(\PDO::FETCH_OBJ);
            }
        } catch (\Exception $eOdbc) {
            // 2. Fallback: Intentar conexión estándar nativa (Local/VPS con sqlsrv)
            try {
                $requisitionId = DB::connection('advisual')->selectOne($sqlQuery, $bindings);
            } catch (\Exception $eNative) {
                throw new \Exception('ODBC Error: '.$eOdbc->getMessage().' | Native Error: '.$eNative->getMessage());
            }
        }

        if (! $requisitionId || ! $requisitionId->id) {
            return null;
        }

        return (int) $requisitionId->id;
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
    /**
     * Annul a batch's requisition in Advisual, but only if purchasing has not
     * worked it yet (no purchase orders). Once an OC exists, purchasing owns the
     * cancellation and must do it on their side.
     *
     * Mirrors what purchasing does by hand (1,200+ real cases follow this exact
     * pattern): RequisicionEstado = 3 plus annulment date and user. Never a
     * DELETE — the row stays as an audit trail and the PO sync already treats
     * this state as cancelled and stops touching the batch.
     *
     * A batch that was never sent has nothing to annul and succeeds trivially.
     */
    public function cancelBatchRequisition(RequisitionBatch $batch, User $cancelledBy): bool
    {
        $reqId = $batch->advisual_requisition_id;

        if (! $reqId) {
            return true;
        }

        try {
            $row = $this->selectAdvisualOne(
                'SELECT COUNT(*) AS c FROM OrdenCompra
                 WHERE OrdenCompraReqCodigo = ? AND ISNULL(OrdenCompraItemDel, 0) = 0',
                [(int) $reqId]
            );

            if ((int) ($row->c ?? 0) > 0) {
                $this->markBatchError($batch, "La requisición {$reqId} ya tiene órdenes de compra en Advisual. Compras debe anularla allá antes de cancelar el lote.");

                return false;
            }

            $this->executeAdvisualWrite(
                'UPDATE Requisicion
                 SET RequisicionEstado = 3,
                     RequisicionAnulacionFecha = GETDATE(),
                     RequisicionAnulacionUsuario = ?
                 WHERE RequisicionCodigo = ?',
                [$cancelledBy->username ?? 'checkmedia', (int) $reqId]
            );

            Log::info('Advisual batch requisition annulled', [
                'batch_id' => $batch->id,
                'requisition_id' => $reqId,
                'cancelled_by' => $cancelledBy->username,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->markBatchError($batch, 'No se pudo anular la requisición en Advisual: '.$e->getMessage());

            return false;
        }
    }

    private function deleteRequisicion(int $requisicionCodigo): void
    {
        $this->executeAdvisualWrite(
            'DELETE FROM Requisicion WHERE RequisicionCodigo = ?;',
            [$requisicionCodigo]
        );
    }

    private function executeAdvisualWrite(string $sql, array $bindings): void
    {
        $this->connector->statement($sql, $bindings);
    }

    private function selectAdvisualOne(string $sql, array $bindings = [])
    {
        return $this->connector->selectOne($sql, $bindings);
    }

    protected function markError(Maintenance $maintenance, string $error): void
    {
        $maintenance->update([
            'advisual_sync_error' => $error,
            'status' => Maintenance::STATUS_PENDING_ADVISUAL,
        ]);

        Log::error('Advisual requisition failed', [
            'maintenance_id' => $maintenance->id,
            'error' => $error,
        ]);
    }

    protected function markBatchError(RequisitionBatch $batch, string $error): void
    {
        $batch->update(['advisual_sync_error' => $error]);

        Log::error('Advisual batch requisition failed', [
            'batch_id' => $batch->id,
            'error' => $error,
        ]);
    }

    /**
     * Record the failure on the batch and leave every maintenance in STATUS_PENDING_ADVISUAL.
     *
     * @param  \Illuminate\Support\Collection<int, Maintenance>  $maintenances
     */
    protected function failBatch(RequisitionBatch $batch, $maintenances, string $error): void
    {
        $this->markBatchError($batch, $error);

        foreach ($maintenances as $maintenance) {
            $this->markError($maintenance, $error);
        }
    }
}
