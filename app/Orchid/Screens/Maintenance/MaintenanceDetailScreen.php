<?php

namespace App\Orchid\Screens\Maintenance;

use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use App\Services\MaintenanceNotificationService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MaintenanceDetailScreen extends Screen
{
    public $maintenance;

    public function query(Maintenance $maintenance): iterable
    {
        $maintenance->load(['advertisingSpace', 'audit.space', 'requestedBy', 'closedBy']);

        $activityLogs = SpaceActivityLog::where('advertising_space_id', $maintenance->advertising_space_id)
            ->whereIn('activity_type', [SpaceActivityLog::TYPE_MAINTENANCE_REQUESTED, SpaceActivityLog::TYPE_MAINTENANCE_CLOSED])
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return [
            'maintenance' => $maintenance,
            'activityLogs' => $activityLogs,
        ];
    }

    public function name(): ?string
    {
        return 'Detalle de Mantenimiento #' . $this->maintenance->id;
    }

    public function description(): ?string
    {
        return 'Información completa del mantenimiento y su estado.';
    }

    public function permission(): ?iterable
    {
        return ['maintenance.view'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Volver a Mantenimientos')
                ->icon('bs.arrow-left')
                ->route('platform.maintenances'),

            Link::make('Ver Auditoría')
                ->icon('bs.file-earmark-text')
                ->route('platform.audit.detail', $this->maintenance->audit_id ?? 0)
                ->canSee((bool) $this->maintenance->audit_id),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.maintenance.detail'),
        ];
    }

    /**
     * Close maintenance from dedicated screen.
     */
    public function close(Request $request, Maintenance $maintenance)
    {
        if (!$maintenance->canBeClosed()) {
            Toast::error('Este mantenimiento no puede ser cerrado.');
            return;
        }

        $request->validate([
            'closure_documents' => 'required|array|min:1|max:10',
            'closure_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'closure_comment' => 'nullable|string|max:1000',
        ], [
            'closure_documents.required' => 'Debe adjuntar al menos un documento de cierre.',
            'closure_documents.max' => 'Máximo 10 documentos de cierre.',
            'closure_documents.*.mimes' => 'Cada archivo debe ser PDF o imagen (JPG, PNG).',
            'closure_documents.*.max' => 'Cada archivo no debe superar 10MB.',
        ]);

        $paths = array_map(
            fn ($file) => $file->store('maintenance-closures', 's3'),
            $request->file('closure_documents')
        );

        $maintenance->update([
            'status' => Maintenance::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'closure_document_path' => $paths[0],
            'closure_document_paths' => $paths,
            'closure_comment' => $request->input('closure_comment'),
        ]);

        SpaceActivityLog::log(
            spaceId: $maintenance->advertising_space_id,
            type: SpaceActivityLog::TYPE_MAINTENANCE_CLOSED,
            description: 'Mantenimiento cerrado desde pantalla de mantenimientos.',
            auditId: $maintenance->audit_id,
            metadata: [
                'maintenance_id' => $maintenance->id,
                'closed_by' => auth()->user()->name,
                'document_paths' => $paths,
            ],
        );

        app(MaintenanceNotificationService::class)->notify('maintenance_closed', $maintenance);

        Toast::success('Mantenimiento cerrado exitosamente.');
    }
}
