<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\SavedReport;
use App\Exports\AuditsExport;
use Maatwebsite\Excel\Facades\Excel;

class AuditReportBuilder extends Component
{
    // Available columns
    public $availableStaticColumns = [];
    public $availableCriteria = [];

    // User selections
    public $selectedColumns = [];

    // Preview data
    public $previewData = null;
    public $showPreview = false;

    // Saved reports
    public $sharedReports = [];
    public $myReports = [];
    public $currentReportId = null;
    public $currentReportName = null;

    // Save modal
    public $showSaveModal = false;
    public $newReportName = '';
    public $newReportDescription = '';
    public $newReportIsShared = false;

    // Active tab
    public $activeTab = 'builder'; // builder, shared, personal, advanced

    public function mount()
    {
        // Define static columns available for selection
        $this->availableStaticColumns = [
            'audit_date' => 'Fecha de Auditoría',
            'auditor' => 'Auditor',
            'city' => 'Ciudad',
            'provider' => 'Proveedor',
            'external_code' => 'Código Externo',
            'general_status' => 'Estado General',
            'observation' => 'Observación',
            'year' => 'Año',
            'week' => 'Semana',
        ];

        // Load active audit criteria for dynamic columns
        $this->availableCriteria = AuditCriterion::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        // Default selection: some common columns
        $this->selectedColumns = [
            'audit_date',
            'auditor',
            'city',
            'external_code',
            'general_status',
        ];

        // Load saved reports
        $this->loadSavedReports();
    }

    /**
     * Load saved reports (shared and personal)
     */
    public function loadSavedReports()
    {
        $userId = auth()->id();

        // Shared reports (available to everyone)
        $this->sharedReports = SavedReport::shared()
            ->standard()
            ->with('user:id,name')
            ->orderBy('name')
            ->get()
            ->toArray();

        // Personal reports (only current user's)
        $this->myReports = SavedReport::personal($userId)
            ->standard()
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Load a saved report configuration
     */
    public function loadReport($reportId)
    {
        $report = SavedReport::find($reportId);

        if (!$report) {
            $this->addError('report', 'El reporte no existe.');
            return;
        }

        // Check access: shared reports are accessible to all, personal only to owner
        if (!$report->is_shared && $report->user_id !== auth()->id()) {
            $this->addError('report', 'No tienes acceso a este reporte.');
            return;
        }

        $this->selectedColumns = $report->selected_columns;
        $this->currentReportId = $report->id;
        $this->currentReportName = $report->name;
        $this->activeTab = 'builder';
        $this->showPreview = false;
        $this->previewData = null;

        session()->flash('message', "Reporte '{$report->name}' cargado.");
    }

    /**
     * Open save modal
     */
    public function openSaveModal()
    {
        $this->newReportName = '';
        $this->newReportDescription = '';
        $this->newReportIsShared = false;
        $this->showSaveModal = true;
    }

    /**
     * Close save modal
     */
    public function closeSaveModal()
    {
        $this->showSaveModal = false;
        $this->resetValidation();
    }

    /**
     * Save current configuration as a new report
     */
    public function saveReport()
    {
        $this->validate([
            'newReportName' => 'required|min:3|max:100',
            'newReportDescription' => 'nullable|max:255',
            'selectedColumns' => 'required|array|min:1',
        ], [
            'newReportName.required' => 'El nombre del reporte es requerido.',
            'newReportName.min' => 'El nombre debe tener al menos 3 caracteres.',
            'selectedColumns.required' => 'Debe seleccionar al menos una columna.',
            'selectedColumns.min' => 'Debe seleccionar al menos una columna.',
        ]);

        // Check permission for shared reports
        if ($this->newReportIsShared && !auth()->user()->hasAccess('reports.create_shared')) {
            $this->addError('newReportIsShared', 'No tienes permiso para crear reportes compartidos.');
            return;
        }

        // Check for duplicate name
        $exists = SavedReport::where('user_id', auth()->id())
            ->where('name', $this->newReportName)
            ->where('is_shared', $this->newReportIsShared)
            ->exists();

        if ($exists) {
            $this->addError('newReportName', 'Ya tienes un reporte con este nombre.');
            return;
        }

        $report = SavedReport::create([
            'user_id' => auth()->id(),
            'name' => $this->newReportName,
            'description' => $this->newReportDescription,
            'selected_columns' => $this->selectedColumns,
            'is_shared' => $this->newReportIsShared,
            'is_advanced' => false,
        ]);

        $this->currentReportId = $report->id;
        $this->currentReportName = $report->name;
        $this->closeSaveModal();
        $this->loadSavedReports();

        session()->flash('message', "Reporte '{$report->name}' guardado exitosamente.");
    }

    /**
     * Delete a saved report
     */
    public function deleteReport($reportId)
    {
        $report = SavedReport::find($reportId);

        if (!$report) {
            return;
        }

        // Only owner can delete
        if ($report->user_id !== auth()->id()) {
            $this->addError('report', 'No puedes eliminar este reporte.');
            return;
        }

        $reportName = $report->name;
        $report->delete();

        // Clear current if it was the deleted one
        if ($this->currentReportId === $reportId) {
            $this->currentReportId = null;
            $this->currentReportName = null;
        }

        $this->loadSavedReports();
        session()->flash('message', "Reporte '{$reportName}' eliminado.");
    }

    /**
     * Clear current report selection
     */
    public function clearCurrentReport()
    {
        $this->currentReportId = null;
        $this->currentReportName = null;
        $this->selectedColumns = [
            'audit_date',
            'auditor',
            'city',
            'external_code',
            'general_status',
        ];
        $this->showPreview = false;
        $this->previewData = null;
    }

    /**
     * Generate preview of the report (first 10 records)
     */
    public function generatePreview()
    {
        // Validate that at least one column is selected
        if (empty($this->selectedColumns)) {
            $this->addError('selectedColumns', 'Debe seleccionar al menos una columna.');
            return;
        }

        // Load data with eager loading for performance
        $this->previewData = Audit::with(['values.criterion', 'user', 'space'])
            ->orderBy('audit_date', 'desc')
            ->limit(10)
            ->get();

        $this->showPreview = true;
    }

    /**
     * Download Excel file with selected columns
     */
    public function downloadExcel()
    {
        // Validate that at least one column is selected
        if (empty($this->selectedColumns)) {
            $this->addError('selectedColumns', 'Debe seleccionar al menos una columna para descargar.');
            return;
        }

        $fileName = 'reporte_auditorias_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new AuditsExport($this->selectedColumns, $this->availableCriteria),
            $fileName
        );
    }

    /**
     * Get display value for a cell in preview
     */
    public function getCellValue($audit, $column)
    {
        if (str_starts_with($column, 'criterion_')) {
            // Dynamic criterion column
            $criterionId = (int) str_replace('criterion_', '', $column);
            $auditValue = $audit->values->firstWhere('audit_criterion_id', $criterionId);
            return $auditValue ? $this->formatValue($auditValue->value) : 'N/A';
        }

        // Static column
        return match($column) {
            'audit_date' => $audit->audit_date?->format('Y-m-d H:i'),
            'auditor' => $audit->user?->name ?? 'N/A',
            'city' => $audit->space?->city ?? 'N/A',
            'provider' => $audit->space?->provider ?? 'N/A',
            'external_code' => $audit->space?->external_code ?? 'N/A',
            'general_status' => $this->formatStatus($audit->general_status),
            'observation' => $audit->observation ?? '',
            'year' => $audit->year,
            'week' => $audit->week,
            default => 'N/A',
        };
    }

    /**
     * Get column heading for display
     */
    public function getColumnHeading($column)
    {
        if (str_starts_with($column, 'criterion_')) {
            $criterionId = (int) str_replace('criterion_', '', $column);
            $criterion = $this->availableCriteria->firstWhere('id', $criterionId);
            return $criterion ? $criterion->name : "Criterio #{$criterionId}";
        }

        return $this->availableStaticColumns[$column] ?? $column;
    }

    /**
     * Format criterion values for display
     */
    protected function formatValue(?string $value): string
    {
        return match($value) {
            'good' => 'Bueno',
            'acceptable' => 'Aceptable',
            'bad' => 'Malo',
            default => $value ?? 'N/A',
        };
    }

    /**
     * Format status for display
     */
    protected function formatStatus(?string $status): string
    {
        return match($status) {
            'good' => 'Bueno',
            'acceptable' => 'Aceptable',
            'bad' => 'Malo',
            default => $status ?? 'N/A',
        };
    }

    /**
     * Check if user can create shared reports
     */
    public function canCreateSharedReports(): bool
    {
        return auth()->user()->hasAccess('reports.create_shared');
    }

    public function render()
    {
        return view('livewire.audit-report-builder');
    }
}
