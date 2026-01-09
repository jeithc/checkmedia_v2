<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Audit;
use App\Models\AuditCriterion;
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

    public function render()
    {
        return view('livewire.audit-report-builder');
    }
}
