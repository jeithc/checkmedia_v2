<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuditConflictException;
use App\Exceptions\AuditOpenMaintenanceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditDetailResource;
use App\Http\Resources\AuditResource;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Services\AuditSubmissionData;
use App\Services\AuditSubmissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuditController extends Controller
{
    public function store(Request $request, AuditSubmissionService $service)
    {
        $validated = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'space_id' => ['required', 'integer'],
            'audit_type' => ['required', 'in:general,structural'],
            'purpose' => ['required', 'in:audit_only,preventive_maintenance'],
            'observation' => ['nullable', 'string'],
            'captured_at' => ['required', 'date', 'before_or_equal:now'],
            'mode' => ['required', 'in:new,complement'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.criterion_id' => ['required', 'integer', 'exists:audit_criteria,id'],
            'values.*.value' => ['required', 'in:good,bad'],
            'values.*.comment' => ['nullable', 'string'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        foreach ($validated['values'] as $i => $val) {
            if ($val['value'] === 'bad' && trim($val['comment'] ?? '') === '') {
                throw ValidationException::withMessages([
                    "values.$i.comment" => ['Describe la irregularidad de este ítem.'],
                ]);
            }
        }

        $space = AdvertisingSpace::findOrFail($validated['space_id']);
        $user = $request->user();

        // Authorize: the user must be allowed to audit, and may only submit an
        // audit_type they hold the matching permission for (admins may do both).
        // Mirrors the capability gating in AuditForm::mount().
        $isStructural = $user->hasAccess('audit.can_audit_structural');
        $isGeneral = $user->hasAccess('audit.can_audit');
        $isAdmin = $user->hasAccess('platform.index');

        abort_unless($isStructural || $isGeneral || $isAdmin, 403, 'No tiene permiso para auditar.');

        if ($validated['audit_type'] === Audit::TYPE_STRUCTURAL) {
            abort_unless($isStructural || $isAdmin, 403, 'No tiene permiso para auditorías estructurales.');
        } else {
            abort_unless($isGeneral || $isAdmin, 403, 'No tiene permiso para auditorías generales.');
        }

        $purpose = $validated['purpose'];
        $canDoPreventive = $user->hasAccess('platform.index') || $user->hasAccess('audit.can_select_purpose');
        if ($purpose === Audit::PURPOSE_PREVENTIVE && ! $canDoPreventive) {
            $purpose = Audit::PURPOSE_AUDIT_ONLY;
        }

        $values = [];
        foreach ($validated['values'] as $val) {
            $values[$val['criterion_id']] = [
                'value' => $val['value'],
                'comment' => $val['comment'] ?? '',
            ];
        }

        $data = new AuditSubmissionData(
            user: $user,
            space: $space,
            auditType: $validated['audit_type'],
            purpose: $purpose,
            values: $values,
            observation: $validated['observation'] ?? null,
            capturedAt: Carbon::parse($validated['captured_at']),
            photos: $request->file('photos'),
            clientUuid: $validated['client_uuid'],
            allowOverwriteExisting: $validated['mode'] === 'complement',
        );

        try {
            $audit = $service->submit($data);
        } catch (AuditConflictException $e) {
            return response()->json([
                'message' => 'Ya existe una auditoría para este espacio en esta semana.',
                'existing_audit' => (new AuditResource($e->existing))->resolve(),
            ], 409);
        } catch (AuditOpenMaintenanceException $e) {
            return response()->json([
                'message' => 'La auditoría tiene un mantenimiento abierto y no puede editarse.',
            ], 422);
        }

        $status = $audit->wasRecentlyCreated ? 201 : 200;

        return (new AuditResource($audit))->response()->setStatusCode($status);
    }

    public function show(Request $request, Audit $audit)
    {
        $user = $request->user();
        $canAudit = $user->hasAccess('audit.can_audit')
            || $user->hasAccess('audit.can_audit_structural')
            || $user->hasAccess('platform.index');
        abort_unless($canAudit, 403, 'No tiene permiso para ver auditorías.');

        $audit->load(['values.criterion', 'photos']);

        return new AuditDetailResource($audit);
    }
}
