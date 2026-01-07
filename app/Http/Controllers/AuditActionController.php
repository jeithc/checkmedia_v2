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
            $value->comment = 'Marcado como Tercero - Automático';
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
        ]);

        $oldStatus = $audit->general_status;

        // 1. Upload Photo
        if ($request->hasFile('revision_photo')) {
            $path = $request->file('revision_photo')->store('audit_resolutions', 'public');
            $audit->resolution_photo_path = $path;
        }

        // 2. Update Audit
        $audit->resolution_comment = $request->input('revision_comment');
        $audit->resolved_at = now();
        $audit->general_status = 'good';
        $audit->save();

        // 3. Log Comment
        $audit->comments()->create([
            'user_id' => auth()->id(),
            'message' => "Cargó revisión: " . ($request->input('revision_comment') ?: 'Sin comentario'),
            'type' => 'resolution',
        ]);

        // 4. Log Activity
        SpaceActivityLog::log(
            spaceId: $audit->advertising_space_id,
            type: SpaceActivityLog::TYPE_RESOLUTION_UPLOADED,
            description: 'Revisión cargada. Auditoría marcada como resuelta.',
            auditId: $audit->id,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => 'good',
                'comment' => $request->input('revision_comment'),
                'photo_path' => $audit->resolution_photo_path,
                'user_name' => auth()->user()->name,
            ],
            year: $audit->year,
            week: $audit->week
        );

        Toast::success('Revisión cargada exitosamente. La auditoría ha sido marcada como resuelta.');

        return back();
    }
}
