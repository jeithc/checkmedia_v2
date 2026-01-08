@push('stylesheets')
    <style>
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            border: none;
            border-bottom: 2px solid transparent;
        }
        .nav-tabs .nav-link:hover {
            color: #495057;
            border-color: transparent;
            isolation: isolate;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd !important;
            background-color: transparent !important;
            border-color: transparent !important;
            border-bottom: 2px solid #0d6efd !important;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 700;
            margin-bottom: 0.25rem;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 400;
        }
    </style>
@endpush

<div class="space-dashboard">
    <!-- Header Metrics -->
    @include('orchid.partials.space-header')

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4 border-bottom" id="spaceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                <i class="bi bi-info-circle me-2"></i>Información General
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                <i class="bi bi-clock-history me-2"></i>Historial de Auditorías
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="spaceTabsContent">
        <!-- General Info Tab -->
        <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
            <div class="row">
                <!-- Left Column: Space Details -->
                <div class="col-md-6 mb-3">
                    <div class="bg-white rounded shadow-sm p-4 h-100">
                        <h5 class="mb-4 text-dark border-bottom pb-2">Detalles del Espacio</h5>
                        
                        <div class="mb-3 border-bottom pb-2">
                            <div class="info-label">Código Externo</div>
                            <div class="info-value">{{ $space->external_code }}</div>
                        </div>

                        <div class="mb-3 border-bottom pb-2">
                            <div class="info-label">Categoría</div>
                            <div class="info-value">{{ $space->category }}</div>
                        </div>

                        <div class="mb-3 border-bottom pb-2">
                            <div class="info-label">Tipo</div>
                            <div class="info-value">{{ $space->type }}</div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Iluminación</div>
                            <div class="info-value">{{ $space->illumination_type }}</div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Location & Provider -->
                <div class="col-md-6 mb-3">
                    <div class="bg-white rounded shadow-sm p-4 h-100">
                        <h5 class="mb-4 text-dark border-bottom pb-2">Ubicación y Proveedor</h5>

                        <div class="mb-3 border-bottom pb-2">
                            <div class="info-label">Ciudad</div>
                            <div class="info-value">{{ $space->city }}</div>
                        </div>

                        <div class="mb-3 border-bottom pb-2">
                            <div class="info-label">Dirección</div>
                            <div class="info-value">{{ $space->address }}</div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Proveedor</div>
                            <div class="info-value">{{ $space->provider }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
            <div class="bg-white rounded shadow-sm p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr class="text-uppercase small text-muted">
                                <th>Año</th>
                                <th>Semana</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Auditor</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($audits as $audit)
                                <tr>
                                    <td>{{ $audit->year }}</td>
                                    <td>{{ $audit->week }}</td>
                                    <td>
                                        @if($audit->audit_date)
                                            {{ $audit->audit_date->format('d M, Y') }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $status = $audit->general_status;
                                            $badgeClass = match($status) {
                                                'good' => 'bg-success bg-opacity-10 text-success',
                                                'bad' => 'bg-danger bg-opacity-10 text-danger',
                                                'warning' => 'bg-warning bg-opacity-10 text-warning',
                                                default => 'bg-secondary bg-opacity-10 text-secondary'
                                            };
                                            $statusText = match($status) {
                                                'good' => 'Bueno',
                                                'bad' => 'Malo',
                                                'warning' => 'Regular',
                                                default => $status
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} px-2 py-1 fw-normal">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $audit->user->name ?? 'N/A' }}
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('platform.audit.detail', $audit->id) }}" class="btn btn-sm btn-link text-primary text-decoration-none">
                                            Ver Reporte <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-clipboard-x fs-1 d-block opacity-25 mb-2"></i>
                                        No hay auditorías registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $audits->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
