<?php

namespace App\Orchid\Screens\Maintenance;

use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
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

        // Conditional validation: support files required when maintenance has RQ
        $supportFilesRule = $maintenance->hasRequisition() ? 'required|array|min:1' : 'nullable|array';

        $request->validate([
            'closure_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'closure_comment' => 'nullable|string|max:1000',
            'support_files' => $supportFilesRule,
            'support_files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'support_files.required' => 'Los archivos de soporte son obligatorios cuando el mantenimiento tiene RQ.',
            'support_files.min' => 'Debe adjuntar al menos un archivo de soporte.',
        ]);

        $path = $request->file('closure_document')->store('maintenance-closures', 'public');

        // Store support files
        $supportFilesPaths = [];
        if ($request->hasFile('support_files')) {
            foreach ($request->file('support_files') as $file) {
                $supportFilesPaths[] = $file->store('maintenance-closures/support', 'public');
            }
        }

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

        SpaceActivityLog::log(
            spaceId: $maintenance->advertising_space_id,
            type: SpaceActivityLog::TYPE_MAINTENANCE_CLOSED,
            description: 'Mantenimiento cerrado desde pantalla de mantenimientos.',
            auditId: $maintenance->audit_id,
            metadata: [
                'maintenance_id' => $maintenance->id,
                'closed_by' => auth()->user()->name,
                'document_path' => $path,
                'support_files_count' => count($supportFilesPaths),
            ],
        );

        Toast::success('Mantenimiento cerrado exitosamente.');
    }
}
