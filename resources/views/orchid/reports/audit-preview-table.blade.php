<div class="bg-white p-4 rounded mt-3">
    @if($showPreview && $previewData && count($selectedColumns) > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr class="text-uppercase small text-muted">
                        @foreach($selectedColumns as $column)
                            <th>
                                @if(str_starts_with($column, 'criterion_'))
                                    @php
                                        $criterionId = (int) str_replace('criterion_', '', $column);
                                        $criterion = $availableCriteria->firstWhere('id', $criterionId);
                                    @endphp
                                    {{ $criterion ? $criterion->name : "Criterio #{$criterionId}" }}
                                @else
                                    {{ $availableStaticColumns[$column] ?? $column }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($previewData as $audit)
                        <tr>
                            @foreach($selectedColumns as $column)
                                <td>
                                    @if(str_starts_with($column, 'criterion_'))
                                        @php
                                            $criterionId = (int) str_replace('criterion_', '', $column);
                                            $auditValue = $audit->values->firstWhere('audit_criterion_id', $criterionId);
                                            $value = $auditValue ? $auditValue->value : null;
                                            $displayValue = match($value) {
                                                'good' => 'Bueno',
                                                'bad' => 'Malo',
                                                default => 'N/A',
                                            };
                                        @endphp
                                        {{ $displayValue }}
                                    @else
                                        @php
                                            $cellValue = match($column) {
                                                'audit_date' => $audit->audit_date?->format('Y-m-d H:i'),
                                                'auditor' => $audit->user?->name ?? 'N/A',
                                                'city' => $audit->space?->city ?? 'N/A',
                                                'provider' => $audit->space?->provider ?? 'N/A',
                                                'external_code' => $audit->space?->external_code ?? 'N/A',
                                                'audit_type' => $audit->audit_type === 'structural' ? 'Estructural' : 'General',
                                                'general_status' => match($audit->general_status) {
                                                    'good' => 'Bueno',
                                                    'bad' => 'Malo',
                                                    default => 'N/A',
                                                },
                                                'observation' => $audit->observation ?? '',
                                                'year' => $audit->year,
                                                'week' => $audit->week,
                                                default => 'N/A',
                                            };
                                        @endphp
                                        {{ $cellValue }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($selectedColumns) }}" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
                                No hay auditorías disponibles para mostrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($previewData->count() > 0)
            <div class="alert alert-info mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Vista Previa:</strong> Mostrando los primeros 10 registros. El archivo Excel contendrá todos los registros disponibles.
            </div>
        @endif
    @else
        <div class="text-center py-5">
            <i class="bi bi-table fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted">Aún no hay vista previa</h5>
            <p class="text-muted small">Seleccione las columnas deseadas y haga clic en "Generar Vista Previa".</p>
        </div>
    @endif
</div>
