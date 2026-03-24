<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAuditRequest;
use App\Http\Resources\Api\AuditResource;
use App\Http\Resources\Api\CriterionResource;
use App\Http\Resources\Api\SpaceResource;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditPhoto;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use App\Services\ImageWatermarkService;
use App\Services\MaintenanceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditApiController extends Controller
{
    // ─── Criteria ───────────────────────────────────────────

    public function criteria(Request $request): JsonResponse
    {
        $category = $request->query('category', Audit::TYPE_GENERAL);

        $criteria = AuditCriterion::where('is_active', true)
            ->forCategory($category)
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'data' => CriterionResource::collection($criteria),
        ]);
    }

    // ─── Spaces ─────────────────────────────────────────────

    public function searchSpace(Request $request): JsonResponse
    {
        $request->validate([
            'external_code' => ['required', 'string'],
        ]);

        $space = AdvertisingSpace::where('external_code', $request->external_code)->first();

        if (! $space) {
            try {
                $syncService = app(\App\Services\AdvisualSyncService::class);
                $space = $syncService->syncSpaceByCcde($request->external_code);
            } catch (\Exception $e) {
                // Sync unavailable
            }
        }

        if (! $space) {
            return response()->json([
                'message' => 'Espacio no encontrado.',
            ], 404);
        }

        $weekData = Audit::getCalendarYearAndWeek(now());
        $auditType = $request->query('audit_type', Audit::TYPE_GENERAL);

        $existingAudit = Audit::where('advertising_space_id', $space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $auditType)
            ->first();

        $booking = $space->getBookingForDate(now());

        return response()->json([
            'data' => [
                'space' => new SpaceResource($space),
                'booking' => $booking ? [
                    'id' => $booking->id,
                    'client_name' => $booking->client_name,
                    'contract_code' => $booking->contract_code,
                    'product_name' => $booking->product_name,
                ] : null,
                'existing_audit' => $existingAudit
                    ? new AuditResource($existingAudit->load('values.criterion', 'photos'))
                    : null,
                'week_info' => $weekData,
            ],
        ]);
    }

    public function showSpace(AdvertisingSpace $space): JsonResponse
    {
        return response()->json([
            'data' => new SpaceResource($space),
        ]);
    }

    // ─── Audits CRUD ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Audit::with(['space', 'user', 'values.criterion', 'photos']);

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('week')) {
            $query->where('week', $request->week);
        }

        $audits = $query->orderByDesc('audit_date')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => AuditResource::collection($audits),
            'meta' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }

    public function store(StoreAuditRequest $request): JsonResponse
    {
        $user = $request->user();
        $space = AdvertisingSpace::where('external_code', $request->external_code)->firstOrFail();

        $auditType = $request->input('audit_type', Audit::TYPE_GENERAL);
        $auditPurpose = $request->input('audit_purpose', Audit::PURPOSE_AUDIT_ONLY);

        if (! $user->is_external && ! $user->hasAnyAccess(['audit.can_audit', 'audit.can_audit_structural'])) {
            return response()->json(['message' => 'No tiene permisos para auditar.'], 403);
        }

        if ($auditType === Audit::TYPE_STRUCTURAL && ! $user->is_external && ! $user->hasAccess('audit.can_audit_structural')) {
            return response()->json(['message' => 'No tiene permisos para auditorías estructurales.'], 403);
        }

        if (! $user->is_external && ! $user->hasAccess('audit.can_select_purpose')) {
            $auditPurpose = Audit::PURPOSE_AUDIT_ONLY;
        }

        $hasIssues = collect($request->values)->contains('value', 'bad');
        if ($hasIssues && empty(trim($request->observation ?? ''))) {
            return response()->json([
                'message' => 'Debe explicar el detalle de la irregularidad en las observaciones.',
                'errors' => ['observation' => ['Requerida cuando hay criterios con valor "bad".']],
            ], 422);
        }

        $date = now();
        $weekData = Audit::getCalendarYearAndWeek($date);

        $approvalStatus = $user->is_external ? Audit::APPROVAL_PENDING : Audit::APPROVAL_APPROVED;

        $audit = Audit::updateOrCreate(
            [
                'advertising_space_id' => $space->id,
                'year' => $weekData['year'],
                'week' => $weekData['week'],
                'audit_type' => $auditType,
            ],
            [
                'user_id' => $user->id,
                'audit_date' => $date,
                'audit_purpose' => $auditPurpose,
                'observation' => $request->observation,
                'general_status' => 'good',
                'source' => Audit::SOURCE_MOBILE,
                'approval_status' => $approvalStatus,
            ]
        );

        $audit->values()->delete();
        $generalStatus = 'good';
        foreach ($request->values as $val) {
            AuditValue::create([
                'audit_id' => $audit->id,
                'audit_criterion_id' => $val['criterion_id'],
                'value' => $val['value'],
            ]);
            if ($val['value'] === 'bad') {
                $generalStatus = 'bad';
            }
        }
        $audit->update(['general_status' => $generalStatus]);

        $watermarkService = new ImageWatermarkService;
        foreach ($request->file('photos', []) as $photo) {
            $watermarked = $watermarkService->addWatermark($photo, $date->format('Y-m-d g:i a'));
            $path = $watermarked->store('audit-photos', 'public');
            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image',
            ]);
        }

        $this->createMaintenanceIfNeeded($audit, $space, $auditPurpose, $user);

        $purposeLabel = $this->getPurposeLabel($auditPurpose);
        SpaceActivityLog::log(
            spaceId: $space->id,
            type: SpaceActivityLog::TYPE_AUDIT_CREATED,
            description: "Auditoría creada vía API ({$purposeLabel}) con estado: {$generalStatus}",
            userId: $user->id,
            auditId: $audit->id,
            metadata: [
                'general_status' => $generalStatus,
                'audit_purpose' => $auditPurpose,
                'source' => 'mobile',
                'is_external' => $user->is_external,
                'photos_count' => count($request->file('photos', [])),
                'user_name' => $user->name,
            ],
            year: $weekData['year'],
            week: $weekData['week']
        );

        if ($generalStatus === 'bad' && $approvalStatus === Audit::APPROVAL_APPROVED) {
            app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit);
        }

        $audit->load(['space', 'user', 'values.criterion', 'photos']);

        $message = $approvalStatus === Audit::APPROVAL_PENDING
            ? 'Auditoría enviada. Quedará pendiente de aprobación por un administrador.'
            : 'Auditoría guardada exitosamente.';

        return response()->json([
            'message' => $message,
            'data' => new AuditResource($audit),
        ], 201);
    }

    public function show(Audit $audit, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external && $audit->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if (! $user->is_external && ! $user->hasAccess('platform.index') && $audit->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $audit->load(['space', 'user', 'values.criterion', 'photos', 'approvedBy']);

        return response()->json([
            'data' => new AuditResource($audit),
        ]);
    }

    public function uploadPhotos(Audit $audit, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($audit->user_id !== $user->id) {
            return response()->json(['message' => 'Solo el autor puede agregar fotos.'], 403);
        }

        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        $watermarkService = new ImageWatermarkService;
        $photoDateTime = $audit->audit_date ?? now();

        foreach ($request->file('photos') as $photo) {
            $watermarked = $watermarkService->addWatermark($photo, $photoDateTime->format('Y-m-d g:i a'));
            $path = $watermarked->store('audit-photos', 'public');
            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image',
            ]);
        }

        return response()->json([
            'message' => 'Fotos agregadas exitosamente.',
            'data' => new AuditResource($audit->load('photos')),
        ]);
    }

    // ─── Admin: Approval system ─────────────────────────────

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $audits = Audit::with(['space', 'user', 'values.criterion', 'photos'])
            ->where('approval_status', Audit::APPROVAL_PENDING)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => AuditResource::collection($audits),
            'meta' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }

    public function approve(Audit $audit, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if (! $audit->isPending()) {
            return response()->json(['message' => 'Esta auditoría ya fue procesada.'], 409);
        }

        $audit->update([
            'approval_status' => Audit::APPROVAL_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: "Auditoría externa aprobada por {$user->name}",
            userId: $user->id,
            auditId: $audit->id,
            metadata: [
                'action' => 'approved',
                'approved_by_name' => $user->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        if ($audit->general_status === 'bad') {
            app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit);
        }

        $audit->load(['space', 'user', 'values.criterion', 'photos', 'approvedBy']);

        return response()->json([
            'message' => 'Auditoría aprobada exitosamente.',
            'data' => new AuditResource($audit),
        ]);
    }

    public function reject(Audit $audit, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if (! $audit->isPending()) {
            return response()->json(['message' => 'Esta auditoría ya fue procesada.'], 409);
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $audit->update([
            'approval_status' => Audit::APPROVAL_REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: "Auditoría externa rechazada por {$user->name}: {$request->reason}",
            userId: $user->id,
            auditId: $audit->id,
            metadata: [
                'action' => 'rejected',
                'rejection_reason' => $request->reason,
                'rejected_by_name' => $user->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        $audit->load(['space', 'user', 'values.criterion', 'photos', 'approvedBy']);

        return response()->json([
            'message' => 'Auditoría rechazada.',
            'data' => new AuditResource($audit),
        ]);
    }

    // ─── Private helpers ────────────────────────────────────

    private function createMaintenanceIfNeeded(Audit $audit, AdvertisingSpace $space, string $purpose, $user): void
    {
        if ($purpose === Audit::PURPOSE_AUDIT_ONLY) {
            return;
        }

        $isStructuralAuditor = ! $user->is_external && $user->hasAccess('audit.can_audit_structural');
        $category = $isStructuralAuditor
            ? strtolower($space->type ?? 'estructural')
            : (request()->input('maintenance_category', 'estructural'));

        if ($purpose === Audit::PURPOSE_PREVENTIVE) {
            Maintenance::create([
                'advertising_space_id' => $space->id,
                'audit_id' => $audit->id,
                'requested_by' => $user->id,
                'requested_at' => now(),
                'type' => Maintenance::TYPE_PREVENTIVE,
                'category' => $category,
                'status' => Maintenance::STATUS_CLOSED,
                'closed_by' => $user->id,
                'closed_at' => now(),
                'description' => 'Mantenimiento preventivo realizado durante auditoría #'.$audit->id,
            ]);
        } elseif ($purpose === Audit::PURPOSE_CORRECTIVE) {
            $maintenance = Maintenance::create([
                'advertising_space_id' => $space->id,
                'audit_id' => $audit->id,
                'requested_by' => $user->id,
                'requested_at' => now(),
                'type' => Maintenance::TYPE_CORRECTIVE,
                'category' => $category,
                'status' => Maintenance::STATUS_REPORTED,
                'description' => $audit->observation ?: 'Mantenimiento correctivo solicitado desde auditoría #'.$audit->id,
            ]);

            app(MaintenanceNotificationService::class)->notify('maintenance_requested', $maintenance);
        }
    }

    private function getPurposeLabel(string $purpose): string
    {
        return match ($purpose) {
            Audit::PURPOSE_PREVENTIVE => 'Mant. Preventivo',
            Audit::PURPOSE_CORRECTIVE => 'Mant. Correctivo',
            default => 'Solo Auditoría',
        };
    }
}
