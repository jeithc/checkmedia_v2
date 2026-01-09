<div class="bg-white p-4 rounded">
    <form id="reportForm" method="POST">
        @csrf
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
                        type="button"
                        onclick="submitForm('{{ route('platform.reports.generate-preview') }}')"
                        class="btn btn-outline-primary w-100"
                    >
                        <i class="bi bi-eye me-1"></i>
                        Generar Vista Previa
                    </button>
                </div>
                <div class="col-sm-6">
                    <button 
                        type="button"
                        onclick="submitForm('{{ route('platform.reports.download-excel') }}')"
                        class="btn btn-success w-100"
                    >
                        <i class="bi bi-download me-1"></i>
                        Descargar Excel
                    </button>
                </div>
            </div>
            
            <div class="mt-2 text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                <span id="columnCount">{{ count($selectedColumns) }}</span> columna(s) seleccionada(s)
            </div>
        </div>
    </form>

    @if(session('error'))
        <div class="alert alert-danger mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ session('error') }}
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update column count on checkbox change
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const count = document.querySelectorAll('.column-checkbox:checked').length;
            const countElement = document.getElementById('columnCount');
            if (countElement) {
                countElement.textContent = count;
            }
        });
    });
});

function submitForm(actionUrl) {
    const selectedColumns = document.querySelectorAll('.column-checkbox:checked');
    
    if (selectedColumns.length === 0) {
        alert('Debe seleccionar al menos una columna.');
        return;
    }
    
    const form = document.getElementById('reportForm');
    if (!form) {
        console.error('Form not found');
        return;
    }
    
    form.action = actionUrl;
    form.submit();
}
</script>

