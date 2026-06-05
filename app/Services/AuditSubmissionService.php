<?php

namespace App\Services;

use App\Exceptions\AuditConflictException;
use App\Exceptions\AuditOpenMaintenanceException;
use App\Models\Audit;
use App\Models\AuditPhoto;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AuditSubmissionService
{
    public function submit(AuditSubmissionData $data): Audit
    {
        if ($data->clientUuid) {
            $existingByUuid = Audit::where('client_uuid', $data->clientUuid)->first();
            if ($existingByUuid) {
                return $existingByUuid;
            }
        }

        $weekData = Audit::getCalendarYearAndWeek($data->capturedAt);

        $existing = Audit::where('advertising_space_id', $data->space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $data->auditType)
            ->first();

        if ($existing && ! $data->allowOverwriteExisting) {
            throw new AuditConflictException($existing);
        }

        if ($existing && $existing->hasOpenMaintenance()) {
            throw new AuditOpenMaintenanceException;
        }

        $photoDateTime = $existing ? ($existing->audit_date ?? $data->capturedAt) : $data->capturedAt;

        $watermarkService = new ImageWatermarkService;
        $uploadedPaths = [];
        foreach ($data->photos as $photo) {
            $watermarked = $watermarkService->addWatermark(
                $photo,
                $photoDateTime->format('Y-m-d g:i a')
            );
            $uploadedPaths[] = $watermarked->store('audit-photos', 's3');
        }

        $generalStatus = 'good';
        foreach ($data->values as $val) {
            if (($val['value'] ?? null) === 'bad') {
                $generalStatus = 'bad';
                break;
            }
        }

        try {
            $audit = DB::transaction(function () use ($data, $weekData, $existing, $generalStatus, $uploadedPaths) {
                $audit = Audit::updateOrCreate(
                    [
                        'advertising_space_id' => $data->space->id,
                        'year' => $weekData['year'],
                        'week' => $weekData['week'],
                        'audit_type' => $data->auditType,
                    ],
                    [
                        'client_uuid' => $data->clientUuid,
                        'user_id' => $data->user->id,
                        'audit_date' => $existing ? $existing->audit_date : $data->capturedAt,
                        'audit_purpose' => $data->purpose,
                        'observation' => $data->observation,
                        'general_status' => $generalStatus,
                    ]
                );

                if (! $audit->wasRecentlyCreated) {
                    $audit->values()->delete();
                }

                foreach ($data->values as $criterionId => $val) {
                    AuditValue::create([
                        'audit_id' => $audit->id,
                        'audit_criterion_id' => $criterionId,
                        'value' => $val['value'],
                        'comment' => $val['value'] === 'bad' ? trim($val['comment'] ?? '') : null,
                    ]);
                }

                foreach ($uploadedPaths as $path) {
                    AuditPhoto::create([
                        'audit_id' => $audit->id,
                        'file_path' => $path,
                        'file_type' => 'image',
                    ]);
                }

                return $audit;
            });
        } catch (QueryException $e) {
            // Concurrent retry of the same offline submission: another request won the
            // race on the client_uuid UNIQUE index. Treat as idempotent success and
            // return the audit that was persisted by the winning request.
            if ($data->clientUuid) {
                $raced = Audit::where('client_uuid', $data->clientUuid)->first();
                if ($raced) {
                    return $raced;
                }
            }

            // A different submission won the race on the [space, year, week, audit_type]
            // unique index. Surface it as a conflict (not a 500) so the caller can
            // complement or discard, matching the pre-insert conflict path.
            $tupleWinner = Audit::where('advertising_space_id', $data->space->id)
                ->where('year', $weekData['year'])
                ->where('week', $weekData['week'])
                ->where('audit_type', $data->auditType)
                ->first();
            if ($tupleWinner && ! $data->allowOverwriteExisting) {
                throw new AuditConflictException($tupleWinner);
            }

            throw $e;
        }

        $this->createMaintenanceIfNeeded($audit, $data);

        $isNew = ! $existing;
        $purposeLabel = $this->purposeLabel($data->purpose);
        SpaceActivityLog::log(
            spaceId: $data->space->id,
            type: $isNew ? SpaceActivityLog::TYPE_AUDIT_CREATED : SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: $isNew
                ? "Auditoría creada ({$purposeLabel}) con estado: {$generalStatus}"
                : "Auditoría actualizada ({$purposeLabel}). Estado: {$generalStatus}",
            userId: $data->user->id,
            auditId: $audit->id,
            metadata: [
                'general_status' => $generalStatus,
                'audit_purpose' => $data->purpose,
                'photos_count' => count($data->photos),
                'user_name' => $data->user->name ?? 'Sistema',
            ],
            year: $weekData['year'],
            week: $weekData['week'],
        );

        if ($generalStatus === 'bad') {
            app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit);
        }

        return $audit;
    }

    protected function createMaintenanceIfNeeded(Audit $audit, AuditSubmissionData $data): void
    {
        if ($data->purpose !== Audit::PURPOSE_PREVENTIVE) {
            return;
        }

        $isStructuralAuditor = $data->user->hasAccess('audit.can_audit_structural')
            && ! $data->user->hasAccess('audit.can_audit');

        $category = $isStructuralAuditor
            ? strtolower($data->space->type ?? 'estructural')
            : 'estructural';

        Maintenance::create([
            'advertising_space_id' => $data->space->id,
            'audit_id' => $audit->id,
            'requested_by' => $data->user->id,
            'requested_at' => now(),
            'type' => Maintenance::TYPE_PREVENTIVE,
            'category' => $category,
            'status' => Maintenance::STATUS_CLOSED,
            'closed_by' => $data->user->id,
            'closed_at' => now(),
            'description' => 'Mantenimiento preventivo realizado durante auditoría #'.$audit->id,
        ]);
    }

    protected function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            Audit::PURPOSE_PREVENTIVE => 'Mant. Preventivo',
            default => 'Solo Auditoría',
        };
    }
}
