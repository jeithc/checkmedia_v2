<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\SpaceActivityLog;
use Illuminate\Http\Request;
use Orchid\Support\Facades\Toast;

class AuditActionController extends Controller
{
    /**
     * Mark audit as Third Party and approve all criteria.
     */
    public function markAsThirdParty(Audit $audit)
    {
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
        $request->validate([
            'revision_photo' => 'required|image|max:10240', // Max 10MB
            'revision_comment' => 'nullable|string',
            'criteria' => 'nullable|array',
            'criteria.*' => 'in:good,acceptable,bad',
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
            } elseif ($value->value === 'acceptable' && $newGeneralStatus !== 'bad') {
                $newGeneralStatus = 'acceptable';
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
        $request->validate([
            'revision_comment' => 'required|string|min:3', // Required edit note
            'criteria' => 'nullable|array',
            'criteria.*' => 'in:good,acceptable,bad',
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
            } elseif ($value->value === 'acceptable' && $newGeneralStatus !== 'bad') {
                $newGeneralStatus = 'acceptable';
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
}
