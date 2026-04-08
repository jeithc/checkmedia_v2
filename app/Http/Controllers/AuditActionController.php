<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use App\Services\AdvisualRequisitionService;
use App\Services\MaintenanceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Orchid\Support\Facades\Toast;

class AuditActionController extends Controller
{
    /**
     * Mark audit as Third Party and approve all criteria.
     */
    public function markAsThirdParty(Audit $audit)
    {
        abort_unless(auth()->user()->hasAccess('audit.close_with_error'), 403);
        $oldStatus = $audit->general_status;

        // 1. Update Space
        $audit->space->is_third_party = true;
        $audit->space->third_party_user_id = auth()->id();
        $audit->space->third_party_modified_at = now();
        $audit->space->save();

        // 2. Update Audit Values
        foreach ($audit->values as $value) {
            $value->value = 'good';
            // Comment removed from audit_values table
            $value->save();
        }

        // 3. Update Audit Status
        $audit->general_status = 'good';
        if (!str_contains($audit->observation ?? '', '[Marcado como Tercero]')) {
            $audit->observation = trim(($audit->observation ?? '') . " [Marcado como Tercero]");
        }
        $audit->save();

        // 4. Log Activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_MARKED_THIRD_PARTY,
            description: 'Espacio marcado como TERCERO. Todos los criterios aprobados automáticamente.',
            auditId: $audit->id,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => 'good',
                'user_name' => auth()->user()->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        Toast::info('La auditoría se ha marcado como Tercero y todos los criterios están Aprobados.');

        return back();
    }

    /**
     * Handle "Cargar Revisión" - Upload proof of fix.
     */
    public function uploadRevision(Request $request, Audit $audit)
    {
        abort_unless(auth()->user()->hasAccess('audit.upload_fixes'), 403);
        $request->validate([
            'revision_photo' => 'required|image|max:10240', // Max 10MB
            'revision_comment' => 'nullable|string',
            'criteria' => 'nullable|array',
            'criteria.*' => 'in:good,bad',
        ]);

        $oldStatus = $audit->general_status;
        $criteriaChanges = [];

        // 1. Update Criteria Values if provided
        if ($request->has('criteria')) {
            foreach ($request->input('criteria') as $valueId => $newValue) {
                $auditValue = $audit->values()->find($valueId);
                if ($auditValue && $auditValue->value !== $newValue) {
                    $criteriaChanges[] = [
                        'criterion' => $auditValue->criterion->name,
                        'old' => $auditValue->value,
                        'new' => $newValue,
                    ];
                    $auditValue->value = $newValue;
                    // Comment removed from audit_values table
                    $auditValue->save();
                }
            }
        }

        // 2. Upload Photo
        if ($request->hasFile('revision_photo')) {
            $path = $request->file('revision_photo')->store('audit_resolutions', 'public');
            $audit->resolution_photo_path = $path;
        }

        // 3. Recalculate General Status based on criteria
        $newGeneralStatus = 'good';
        foreach ($audit->values()->get() as $value) {
            if ($value->value === 'bad') {
                $newGeneralStatus = 'bad';
                break;
        }
        }

        // 4. Update Audit
        $audit->resolution_comment = $request->input('revision_comment');
        $audit->resolved_at = now();
        $audit->general_status = $newGeneralStatus;
        $audit->save();

        // 5. Log Comment
        $audit->comments()->create([
            'user_id' => auth()->id(),
            'message' => "Cargó revisión: " . ($request->input('revision_comment') ?: 'Sin comentario'),
            'type' => 'resolution',
        ]);

        // 6. Log Activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_RESOLUTION_UPLOADED,
            description: 'Revisión cargada. Estado actualizado a: ' . ucfirst($newGeneralStatus),
            auditId: $audit->id,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => $newGeneralStatus,
                'comment' => $request->input('revision_comment'),
                'photo_path' => $audit->resolution_photo_path,
                'criteria_changes' => $criteriaChanges,
                'user_name' => auth()->user()->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        $message = 'Revisión cargada exitosamente.';
        if (count($criteriaChanges) > 0) {
            $message .= ' Se actualizaron ' . count($criteriaChanges) . ' criterio(s).';
        }

        Toast::success($message);

        return back();
    }

    /**
     * Handle "Editar Auditoría" - Update criteria and observation.
     */
    public function updateAudit(Request $request, Audit $audit)
    {
        abort_unless(auth()->user()->hasAccess('audit.close_with_error'), 403);
        $request->validate([
            'revision_comment' => 'required|string|min:3', // Required edit note
            'criteria' => 'nullable|array',
            'criteria.*' => 'in:good,bad',
        ], [
            'revision_comment.required' => 'La nota de edición es obligatoria.',
            'revision_comment.min' => 'La nota debe tener al menos 3 caracteres.',
        ]);

        $oldStatus = $audit->general_status;
        $criteriaChanges = [];

        // 1. Update Criteria Values if provided
        if ($request->has('criteria')) {
            foreach ($request->input('criteria') as $valueId => $newValue) {
                $auditValue = $audit->values()->find($valueId);
                if ($auditValue && $auditValue->value !== $newValue) {
                    $criteriaChanges[] = [
                        'criterion' => $auditValue->criterion->name,
                        'old' => $auditValue->value,
                        'new' => $newValue,
                    ];
                    $auditValue->value = $newValue;
                    $auditValue->save();
                }
            }
        }

        // 2. Recalculate General Status based on criteria
        $newGeneralStatus = 'good';
        foreach ($audit->values()->get() as $value) {
            if ($value->value === 'bad') {
                $newGeneralStatus = 'bad';
                break;
        }
        }

        // 3. Add Edit Note as Comment (Do not overwrite initial observation)
        if ($request->filled('revision_comment')) {
            $audit->comments()->create([
                'user_id' => auth()->id(),
                'message' => $request->input('revision_comment'),
                'type' => 'edit_note',
            ]);
        }

        $audit->general_status = $newGeneralStatus;
        $audit->save();

        // 4. Log Activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: 'Auditoría editada manualmente. Estado: ' . ucfirst($newGeneralStatus),
            auditId: $audit->id,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => $newGeneralStatus,
                'added_comment' => $request->input('revision_comment'),
                'criteria_changes' => $criteriaChanges,
                'user_name' => auth()->user()->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        Toast::success('Auditoría actualizada exitosamente.');

        return back();
    }

    /**
     * Request maintenance from audit detail.
     */
    public function requestMaintenance(Request $request, Audit $audit)
    {
        abort_unless(auth()->user()->hasAccess('audit.request_maintenance'), 403);

        $request->validate([
            'maintenance_type' => 'required|in:corrective,preventive',
            'maintenance_category' => 'required|in:estructural,electrico,ambiental,material',
            'maintenance_priority' => 'required|in:alta,media,baja',
            'maintenance_description' => 'required|string|min:5',
        ], [
            'maintenance_type.required' => 'El tipo de mantenimiento es requerido.',
            'maintenance_category.required' => 'La categoría es requerida.',
            'maintenance_priority.required' => 'La prioridad es requerida.',
            'maintenance_description.required' => 'La descripción es requerida.',
            'maintenance_description.min' => 'La descripción debe tener al menos 5 caracteres.',
        ]);

        // Validate audit has bad status
        if ($audit->general_status !== 'bad') {
            return response()->json(['message' => 'Solo se pueden solicitar mantenimientos para auditorías con errores.'], 422);
        }

        // Check no open maintenance exists for this audit
        if ($audit->hasOpenMaintenance()) {
            return response()->json(['message' => 'Ya existe un mantenimiento abierto para esta auditoría.'], 422);
        }

        // Check Advisual connectivity before creating anything
        $advisualService = app(AdvisualRequisitionService::class);
        try {
            DB::connection('advisual')->getPdo();
        } catch (\Exception $e) {
            Log::error('Advisual connection failed before creating maintenance', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo conectar con Advisual. La requisición no fue creada.'], 503);
        }

        // Create maintenance record
        $maintenance = Maintenance::create([
            'advertising_space_id' => $audit->advertising_space_id,
            'audit_id' => $audit->id,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
            'type' => $request->input('maintenance_type'),
            'category' => $request->input('maintenance_category'),
            'status' => Maintenance::STATUS_REPORTED,
            'priority' => $request->input('maintenance_priority'),
            'description' => $request->input('maintenance_description'),
        ]);

        // Create requisition in Advisual
        $synced = $advisualService->createRequisition($maintenance);

        if (!$synced) {
            $maintenance->delete();
            return response()->json(['message' => 'Error al crear la requisición en Advisual. El mantenimiento no fue creado.'], 500);
        }

        // Log activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_MAINTENANCE_REQUESTED,
            description: 'Mantenimiento ' . ($request->input('maintenance_type') === 'preventive' ? 'preventivo' : 'correctivo') . ' solicitado. Categoría: ' . $request->input('maintenance_category') . '. Prioridad: ' . $request->input('maintenance_priority'),
            auditId: $audit->id,
            metadata: [
                'maintenance_id' => $maintenance->id,
                'type' => $request->input('maintenance_type'),
                'category' => $request->input('maintenance_category'),
                'priority' => $request->input('maintenance_priority'),
                'advisual_synced' => $synced,
                'user_name' => auth()->user()->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        app(MaintenanceNotificationService::class)->notify('maintenance_requested', $maintenance);

        return response()->json(['message' => 'Mantenimiento solicitado exitosamente.', 'success' => true]);
    }

    /**
     * Close maintenance from audit detail (with PDF/image upload).
     */
    public function closeMaintenanceFromAudit(Request $request, Audit $audit)
    {
        abort_unless(auth()->user()->hasAccess('maintenance.close'), 403);

        // Find the open maintenance BEFORE validation (need to check for RQ)
        $maintenance = $audit->maintenances()
            ->whereNotIn('status', [Maintenance::STATUS_CLOSED])
            ->first();

        if (!$maintenance) {
            Toast::error('No se encontró un mantenimiento abierto para esta auditoría.');
            return back();
        }

        if (!$maintenance->canBeClosed()) {
            Toast::error('Este mantenimiento no puede ser cerrado.');
            return back();
        }

        // Conditional validation: support files required when maintenance has RQ
        $supportFilesRule = $maintenance->hasRequisition() ? 'required|array|min:1' : 'nullable|array';

        $request->validate([
            'closure_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'closure_comment' => 'nullable|string|max:1000',
            'support_files' => $supportFilesRule,
            'support_files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'closure_document.required' => 'El documento de cierre es requerido.',
            'closure_document.mimes' => 'El archivo debe ser PDF o imagen (JPG, PNG).',
            'closure_document.max' => 'El archivo no debe superar 10MB.',
            'support_files.required' => 'Los archivos de soporte son obligatorios cuando el mantenimiento tiene RQ.',
            'support_files.min' => 'Debe adjuntar al menos un archivo de soporte.',
            'support_files.*.mimes' => 'Los archivos de soporte deben ser PDF o imagen (JPG, PNG).',
            'support_files.*.max' => 'Cada archivo de soporte no debe superar 10MB.',
        ]);

        // Store closure document
        $path = $request->file('closure_document')->store('maintenance-closures', 'public');

        // Store support files
        $supportFilesPaths = [];
        if ($request->hasFile('support_files')) {
            foreach ($request->file('support_files') as $file) {
                $supportFilesPaths[] = $file->store('maintenance-closures/support', 'public');
            }
        }

        // Close the maintenance
        $updateData = [
            'status' => Maintenance::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'closure_document_path' => $path,
            'closure_comment' => $request->input('closure_comment'),
        ];

        if (!empty($supportFilesPaths)) {
            $updateData['support_files_paths'] = $supportFilesPaths;
        }

        $maintenance->update($updateData);

        // Log activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_MAINTENANCE_CLOSED,
            description: 'Mantenimiento cerrado desde detalle de auditoría.',
            auditId: $audit->id,
            metadata: [
                'maintenance_id' => $maintenance->id,
                'closed_by' => auth()->user()->name,
                'document_path' => $path,
                'support_files_count' => count($supportFilesPaths),
            ],
            year: $audit->year,
            week: $audit->week
        );

        app(MaintenanceNotificationService::class)->notify('maintenance_closed', $maintenance);

        Toast::success('Mantenimiento cerrado exitosamente.');
        return back();
    }
}
