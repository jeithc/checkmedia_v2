<form id="reportForm" method="POST" class="report-builder-form">
    @csrf
    
    {{-- Column Selection Section --}}
    <div class="bg-white p-4 rounded mb-3">
        <div class="mb-3 pb-3 border-bottom">
            <h5 class="mb-1">
                <i class="bi bi-funnel me-2"></i>
                Selección de Columnas
            </h5>
            <p class="text-muted small mb-0">
                <span id="columnCount">{{ count($selectedColumns) }}</span> columna(s) seleccionada(s)
            </p>
        </div>

        <div class="row g-3 mb-3">
            {{-- Static Columns --}}
            <div class="col-md-6">
                <h6 class="text-muted small fw-bold mb-3">
                    <i class="bi bi-file-text me-1"></i>
                    DATOS GENERALES
                </h6>
                <div style="max-height: 400px; overflow-y: auto;">
                    @foreach($availableStaticColumns as $key => $label)
                        <div class="form-check mb-2">
                            <input 
                                class="form-check-input column-checkbox" 
                                type="checkbox" 
                                name="selectedColumns[]" 
                                value="{{ $key }}" 
                                id="col_{{ $key }}"
                                {{ in_array($key, $selectedColumns) ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="col_{{ $key }}">
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Dynamic Criteria Columns --}}
            <div class="col-md-6">
                <h6 class="text-muted small fw-bold mb-3">
                    <i class="bi bi-check2-square me-1"></i>
                    PREGUNTAS DE AUDITORÍA
                </h6>
                <div style="max-height: 400px; overflow-y: auto;">
                    @forelse($availableCriteria as $criterion)
                        <div class="form-check mb-2">
                            <input 
                                class="form-check-input column-checkbox" 
                                type="checkbox" 
                                name="selectedColumns[]" 
                                value="criterion_{{ $criterion->id }}" 
                                id="col_criterion_{{ $criterion->id }}"
                                {{ in_array('criterion_'.$criterion->id, $selectedColumns) ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="col_criterion_{{ $criterion->id }}">
                                {{ $criterion->name }}
                            </label>
                        </div>
                    @empty
                        <p class="text-muted small fst-italic">No hay criterios activos disponibles.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="border-top pt-3">
            <div class="row g-2">
                <div class="col-sm-6">
                    <button 
                        type="submit"
                        formaction="{{ route('platform.reports.generate-preview') }}"
                        formtarget="_self"
                        data-turbo="true"
                        class="btn btn-outline-primary w-100"
                    >
                        <i class="bi bi-eye me-1"></i>
                        Generar Vista Previa
                    </button>
                </div>
                <div class="col-sm-6">
                    <button 
                        type="submit"
                        formaction="{{ route('platform.reports.download-excel') }}"
                        formtarget="_blank"
                        data-turbo="false"
                        class="btn btn-success w-100"
                    >
                        <i class="bi bi-download me-1"></i>
                        Descargar Excel
                    </button>
                </div>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ session('error') }}
            </div>
        @endif
    </div>
</form>

{{-- Preview Table - Outside form --}}
@if($showPreview && $previewData && count($selectedColumns) > 0)
    <div class="bg-white p-4 rounded">
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
                                                'acceptable' => 'Aceptable',
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
                                                'general_status' => match($audit->general_status) {
                                                    'good' => 'Bueno',
                                                    'acceptable' => 'Aceptable',
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
    </div>
@else
    <div class="bg-white p-4 rounded text-center">
        <i class="bi bi-table fs-1 text-muted opacity-25 mb-3"></i>
        <h5 class="text-muted">Aún no hay vista previa</h5>
        <p class="text-muted small">Seleccione las columnas deseadas y haga clic en "Generar Vista Previa".</p>
    </div>
@endif

<script>
// Update column count on checkbox change (simple vanilla JS)
(function() {
    function updateColumnCount() {
        const count = document.querySelectorAll('.column-checkbox:checked').length;
        const countElement = document.getElementById('columnCount');
        if (countElement) {
            countElement.textContent = count;
        }
    }
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.column-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', updateColumnCount);
            });
        });
    } else {
        document.querySelectorAll('.column-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateColumnCount);
        });
    }
})();
</script>
