<?php

namespace App\Exports;

use App\Models\Audit;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditsExport implements FromQuery, WithHeadings, WithMapping
{
    protected array $selectedColumns;
    protected array $staticColumnDefinitions;
    protected $criteria;
    protected array $filters;

    public function __construct(array $selectedColumns, $criteria, array $filters = [])
    {
        $this->selectedColumns = $selectedColumns;
        $this->criteria = $criteria;
        $this->filters = $filters;

        // Define all available static columns
        $this->staticColumnDefinitions = [
            'audit_date' => 'Fecha de Auditoría',
            'auditor' => 'Auditor',
            'city' => 'Ciudad',
            'provider' => 'Proveedor',
            'external_code' => 'Código Externo',
            'audit_type' => 'Tipo de Auditoría',
            'general_status' => 'Estado General',
            'observation' => 'Observación',
            'year' => 'Año',
            'week' => 'Semana',
        ];
    }

    /**
     * Build the query with eager loading for performance
     */
    public function query()
    {
        $query = Audit::query()
            ->with(['values.criterion', 'user', 'space'])
            ->orderBy('audit_date', 'desc');

        if (!empty($this->filters['dateFrom'])) {
            $query->whereDate('audit_date', '>=', $this->filters['dateFrom']);
        }
        if (!empty($this->filters['dateTo'])) {
            $query->whereDate('audit_date', '<=', $this->filters['dateTo']);
        }
        if (!empty($this->filters['city'])) {
            $query->whereHas('space', fn($q) => $q->where('city', 'like', "%{$this->filters['city']}%"));
        }
        if (!empty($this->filters['auditType'])) {
            $query->where('audit_type', $this->filters['auditType']);
        }

        return $query;
    }

    /**
     * Generate column headings based on selected columns
     */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->selectedColumns as $column) {
            if (str_starts_with($column, 'criterion_')) {
                // Dynamic criterion column
                $criterionId = (int) str_replace('criterion_', '', $column);
                $criterion = $this->criteria->firstWhere('id', $criterionId);
                $headings[] = $criterion ? $criterion->name : "Criterio #{$criterionId}";
            } else {
                // Static column
                $headings[] = $this->staticColumnDefinitions[$column] ?? $column;
            }
        }

        return $headings;
    }

    /**
     * Map each audit record to a row array
     */
    public function map($audit): array
    {
        $row = [];

        foreach ($this->selectedColumns as $column) {
            if (str_starts_with($column, 'criterion_')) {
                // Dynamic criterion column - pivot logic
                $criterionId = (int) str_replace('criterion_', '', $column);
                $auditValue = $audit->values->firstWhere('audit_criterion_id', $criterionId);
                $row[] = $auditValue ? $this->formatValue($auditValue->value) : 'N/A';
            } else {
                // Static column - direct mapping
                $row[] = match ($column) {
                    'audit_date' => $audit->audit_date?->format('Y-m-d'),
                    'auditor' => $audit->user?->name ?? 'N/A',
                    'city' => $audit->space?->city ?? 'N/A',
                    'provider' => $audit->space?->provider ?? 'N/A',
                    'external_code' => $audit->space?->external_code ?? 'N/A',
                    'audit_type' => $audit->audit_type === 'structural' ? 'Estructural' : 'General',
                    'general_status' => $this->formatStatus($audit->general_status),
                    'observation' => $audit->observation ?? '',
                    'year' => $audit->year,
                    'week' => $audit->week,
                    default => 'N/A',
                };
            }
        }

        return $row;
    }

    protected function formatValue(?string $value): string
    {
        return match ($value) {
            'good' => 'Bueno',
            'bad' => 'Malo',
            default => $value ?? 'N/A',
        };
    }

    protected function formatStatus(?string $status): string
    {
        return match ($status) {
            'good' => 'Bueno',
            'bad' => 'Malo',
            default => $status ?? 'N/A',
        };
    }
}
