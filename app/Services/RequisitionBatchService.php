<?php

namespace App\Services;

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequisitionBatchService
{
    /**
     * The only maintenance type accepted in a batch (v1).
     */
    const ALLOWED_TYPE = 'preventivo';

    /**
     * A batch with the same codes from the same user inside this window is a
     * form re-submit, not a new request. Ten minutes is far longer than the
     * ~60s the real incident spanned, and far shorter than any legitimate
     * reason to send the same list twice.
     */
    const DUPLICATE_WINDOW_MINUTES = 10;

    /**
     * Resolved lazily so the service stays newable without arguments.
     */
    protected ?AdvisualSyncService $sync;

    public function __construct(?AdvisualSyncService $sync = null)
    {
        $this->sync = $sync;
    }

    /**
     * Parse the pasted CSV/TSV into rows.
     *
     * Expected format per line: cod_espacio,tipo,descripcion
     * Separator is comma or tab, detected per line (Excel pastes with tabs).
     * Empty lines are skipped. A header line is skipped when the first cell of
     * the first data line is not numeric.
     *
     * @return array<int, array{line_number: int, external_code: string, type: string, description: string}>
     */
    public function parseCsv(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        $rows = [];
        $isFirstLine = true;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (trim($line) === '') {
                continue;
            }

            $separator = str_contains($line, "\t") ? "\t" : ',';
            $cells = str_getcsv($line, $separator, '"', '');

            $externalCode = trim((string) ($cells[0] ?? ''));
            $type = trim((string) ($cells[1] ?? ''));
            $description = trim((string) ($cells[2] ?? ''));

            if ($isFirstLine) {
                $isFirstLine = false;

                // Header row: first cell is not a space code.
                if (! is_numeric($externalCode)) {
                    continue;
                }
            }

            $rows[] = [
                'line_number' => $lineNumber,
                'external_code' => $externalCode,
                'type' => $type,
                'description' => $description,
            ];
        }

        return $rows;
    }

    /**
     * Validate parsed rows before touching Advisual. All-or-nothing: the caller
     * must not create anything when this returns a non-empty list.
     *
     * @param  array<int, array{line_number: int, external_code: string, type: string, description: string}>  $rows
     * @return array<int, array{line_number: int, message: string}>
     */
    public function validateRows(array $rows): array
    {
        $errors = [];

        if (empty($rows)) {
            return [['line_number' => 0, 'message' => 'No hay filas para procesar.']];
        }

        $codes = array_values(array_filter(array_map(
            fn ($row) => (string) ($row['external_code'] ?? ''),
            $rows
        ), fn ($code) => $code !== ''));

        // external_code is a string in Advisual (2, 3 and 5 digit codes exist):
        // never cast it to int for comparison.
        $existingCodes = AdvertisingSpace::query()
            ->whereIn('external_code', $codes)
            ->pluck('external_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $existingCodes = array_flip($existingCodes);

        // Spaces reach CheckMedia lazily: AuditForm imports one from Advisual the
        // first time an auditor types its code. A batch would otherwise reject
        // codes that are perfectly valid in Advisual but never audited yet, so
        // import the missing ones here before calling them non-existent.
        foreach (array_unique($codes) as $code) {
            if (isset($existingCodes[$code])) {
                continue;
            }

            if ($this->importSpace($code)) {
                $existingCodes[$code] = true;
            }
        }

        $seen = [];

        foreach ($rows as $row) {
            $lineNumber = $row['line_number'] ?? 0;
            $externalCode = trim((string) ($row['external_code'] ?? ''));
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $description = trim((string) ($row['description'] ?? ''));

            if ($externalCode === '') {
                $errors[] = ['line_number' => $lineNumber, 'message' => 'El código de espacio es requerido.'];
            } elseif (! isset($existingCodes[$externalCode])) {
                $errors[] = ['line_number' => $lineNumber, 'message' => "El espacio '{$externalCode}' no existe en Advisual."];
            } elseif (isset($seen[$externalCode])) {
                $errors[] = [
                    'line_number' => $lineNumber,
                    'message' => "El espacio '{$externalCode}' está duplicado (ya aparece en la línea {$seen[$externalCode]}).",
                ];
            } else {
                $seen[$externalCode] = $lineNumber;
            }

            if ($type !== self::ALLOWED_TYPE) {
                $errors[] = [
                    'line_number' => $lineNumber,
                    'message' => "Tipo de mantenimiento no soportado: '{$row['type']}'. Solo se admite 'preventivo'.",
                ];
            }

            if ($description === '') {
                $errors[] = ['line_number' => $lineNumber, 'message' => 'La descripción es requerida.'];
            }
        }

        return $errors;
    }

    /**
     * Create the batch and its N maintenances inside a local DB transaction.
     *
     * Advisual is an external DB and does not take part in this transaction:
     * the caller sends the requisition after this returns.
     *
     * @param  array<int, array{line_number: int, external_code: string, type: string, description: string}>  $rows
     */
    /**
     * Pull a space from Advisual into advertising_spaces.
     *
     * Returns false when Advisual does not have it either, or when the lookup
     * fails: a batch must never be blocked by a sync error it cannot explain,
     * so the row falls through to the regular "no existe" validation error.
     */
    protected function importSpace(string $code): bool
    {
        try {
            $this->sync ??= app(AdvisualSyncService::class);

            return $this->sync->syncSpaceByCcde($code) !== null;
        } catch (\Throwable $e) {
            Log::warning('No se pudo importar el espacio desde Advisual para el lote', [
                'external_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Find a batch this user created recently with exactly the same set of codes.
     *
     * Prod incident 2026-08-24: one 58-space list was posted three times in ~60s
     * (user retried while the first request was still talking to Advisual),
     * producing three batches and two duplicate requisitions. The browser can
     * disable the button, but only the server can stop a reload or a back-button
     * re-post, so the check lives here.
     */
    public function findRecentDuplicate(array $rows, User $user): ?RequisitionBatch
    {
        // Codes are exact strings everywhere in this repo: '703' and '0703' are
        // different spaces and '0' is a valid code. Loose unique()/filter()/sort()
        // would merge or drop them and miss a real duplicate.
        $codes = collect($rows)
            ->map(fn ($row) => trim((string) ($row['external_code'] ?? '')))
            ->filter(fn ($code) => $code !== '')
            ->unique(strict: true)
            ->sort(SORT_STRING)
            ->values();

        if ($codes->isEmpty()) {
            return null;
        }

        // A cancelled batch is not a duplicate: cancelling and re-sending the
        // same list is a legitimate flow and must not be blocked for 10 minutes.
        $candidates = RequisitionBatch::query()
            ->where('created_by', $user->id)
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->withCount('maintenances')
            ->latest()
            ->get()
            ->where('maintenances_count', $codes->count());

        foreach ($candidates as $batch) {
            $batchCodes = $batch->maintenances()
                ->join('advertising_spaces', 'advertising_spaces.id', '=', 'maintenances.advertising_space_id')
                ->pluck('advertising_spaces.external_code')
                ->map(fn ($code) => (string) $code)
                ->sort(SORT_STRING)
                ->values();

            if ($batchCodes->all() === $codes->all()) {
                return $batch;
            }
        }

        return null;
    }

    /**
     * Cancel a batch locally, once Advisual has been (or did not need to be) annulled.
     *
     * Maintenances are closed, not deleted: the whole point of a batch is the
     * audit trail, and a closed maintenance with no purchase order never reaches
     * the cost chart. Caller must run cancelBatchRequisition() first — this only
     * touches the local DB.
     */
    public function cancelBatch(RequisitionBatch $batch, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($batch, $user, $reason) {
            $now = now();

            $batch->maintenances()
                ->where('status', '!=', Maintenance::STATUS_CLOSED)
                ->update([
                    'status' => Maintenance::STATUS_CLOSED,
                    'closed_by' => $user->id,
                    'closed_at' => $now,
                    'closure_comment' => Maintenance::CLOSURE_CANCELLED_PREFIX.' Lote cancelado'.($reason ? ': '.$reason : '.'),
                ]);

            $batch->update([
                'cancelled_at' => $now,
                'cancelled_by' => $user->id,
                'advisual_sync_error' => null,
            ]);
        });
    }

    /**
     * Duplicate check + create as one atomic step.
     *
     * findRecentDuplicate() alone is a read: two concurrent valid posts (two
     * tabs) could both pass it before either inserts, and each would create a
     * batch and an Advisual requisition. Serialising on the user's row with
     * lockForUpdate() inside the transaction makes the second post wait and then
     * see the first batch. The cache driver is file-based in prod, so a DB row
     * lock is the only lock that is actually atomic across PHP workers.
     *
     * @return array{0: RequisitionBatch, 1: bool} [batch, isNew]
     */
    public function createBatchUnlessDuplicate(string $name, ?string $city, array $rows, User $user): array
    {
        return DB::transaction(function () use ($name, $city, $rows, $user) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($existing = $this->findRecentDuplicate($rows, $user)) {
                return [$existing, false];
            }

            return [$this->createBatch($name, $city, $rows, $user), true];
        });
    }

    public function createBatch(string $name, ?string $city, array $rows, User $user): RequisitionBatch
    {
        return DB::transaction(function () use ($name, $city, $rows, $user) {
            $batch = RequisitionBatch::create([
                'name' => $name,
                'city' => $city,
                'created_by' => $user->id,
            ]);

            $codes = array_map(fn ($row) => trim((string) ($row['external_code'] ?? '')), $rows);

            $spaceIds = AdvertisingSpace::query()
                ->whereIn('external_code', $codes)
                ->pluck('id', 'external_code');

            $line = 0;

            foreach ($rows as $row) {
                $externalCode = trim((string) ($row['external_code'] ?? ''));
                $line++;

                Maintenance::create([
                    'advertising_space_id' => $spaceIds[$externalCode] ?? null,
                    'audit_id' => null,
                    'requested_by' => $user->id,
                    'requested_at' => now(),
                    'type' => Maintenance::TYPE_PREVENTIVE,
                    'category' => self::ALLOWED_TYPE,
                    'status' => Maintenance::STATUS_REPORTED,
                    'description' => trim((string) ($row['description'] ?? '')),
                    'requisition_batch_id' => $batch->id,
                    'advisual_requisition_line' => $line,
                ]);
            }

            return $batch;
        });
    }
}
