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

    public function __construct(array $selectedColumns, $criteria)
    {
        $this->selectedColumns = $selectedColumns;
        $this->criteria = $criteria;
        
        // Define all available static columns
        $this->staticColumnDefinitions = [
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
    }

    /**
     * Build the query with eager loading for performance
     */
    public function query()
    {
        return Audit::query()
            ->with(['values.criterion', 'user', 'space'])
            ->orderBy('audit_date', 'desc');
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
     * This is where the magic happens - pivoting audit_values to columns
     */
    public function map($audit): array
    {
        $row = [];

        foreach ($this->selectedColumns as $column) {
            if (str_starts_with($column, 'criterion_')) {
                // Dynamic criterion column - pivot logic
                $criterionId = (int) str_replace('criterion_', '', $column);
                
                // Find the value for this criterion in the audit's values collection
                $auditValue = $audit->values->firstWhere('audit_criterion_id', $criterionId);
                
                // If found, use the value, otherwise N/A
                $row[] = $auditValue ? $this->formatValue($auditValue->value) : 'N/A';
            } else {
                // Static column - direct mapping
                $row[] = match($column) {
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
        }

        return $row;
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
}
