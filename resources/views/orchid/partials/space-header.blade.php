<div class="row mb-3">
    <!-- Summary Cards -->
    <div class="col-md-4 mb-2">
        <div class="bg-white rounded shadow-sm p-4 h-100 d-flex flex-column justify-content-center">
            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Total Auditorías</small>
            <span class="fs-2 fw-light text-dark">{{ $metrics['total_audits'] }}</span>
        </div>
    </div>
    
    <div class="col-md-4 mb-2">
        <div class="bg-white rounded shadow-sm p-4 h-100 d-flex flex-column justify-content-center">
            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Última Auditoría</small>
            <span class="fs-2 fw-light text-dark">{{ $metrics['last_audit'] }}</span>
        </div>
    </div>
    
    <div class="col-md-4 mb-2">
        <div class="bg-white rounded shadow-sm p-4 h-100 d-flex flex-column justify-content-center">
            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Estado Actual</small>
            <div>
                @php
                    $status = $metrics['status'] ?? 'N/A';
                    $badgeClass = match($status) {
                        'good' => 'bg-success text-white',
                        'bad' => 'bg-danger text-white',
                        'warning' => 'bg-warning text-dark',
                        default => 'bg-secondary text-white'
                    };
                    $statusText = match($status) {
                        'good' => 'Bueno',
                        'bad' => 'Malo',
                        'warning' => 'Regular',
                        default => 'Sin Datos'
                    };
                    $icon = match($status) {
                        'good' => 'bi-check-circle-fill',
                        'bad' => 'bi-x-circle-fill',
                        'warning' => 'bi-exclamation-circle-fill',
                        default => 'bi-question-circle-fill'
                    };
                @endphp
                <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2" style="font-size: 1rem; font-weight: 500;">
                    <i class="bi {{ $icon }}"></i>
                    {{ $statusText }}
                </span>
            </div>
        </div>
    </div>
</div>
