@push('head')
    <style>
        .input-group-sm .input-group-text, .input-group-sm .form-control {
            min-height: 31px; /* Force consistent height for sm inputs */
        }
        
        /* Space Browser Redesign Styles from space-browser.css */
        .filter-group {
            min-width: 150px;
        }
        .filter-group label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .table thead th {
            font-weight: 600;
            color: #6b7280;
            border-bottom-width: 1px;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .table tbody td {
            vertical-align: middle;
            font-size: 0.9em;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .badge {
            font-weight: 500;
            letter-spacing: 0.025em;
        }
    </style>
@endpush

<div class="space-browser-container p-4 bg-white rounded shadow-sm">
    <!-- Filters Toolbar -->
    <div class="filter-toolbar mb-4">
        <div class="row g-3">
            <!-- Category Filter -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Categoría</label>
                    <select class="form-select form-select-sm" wire:model.live="filterCategory">
                        <option value="">(Todos)</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- City Filter -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Ciudad</label>
                    <select class="form-select form-select-sm" wire:model.live="filterCity">
                        <option value="">(Todas)</option>
                        @foreach($cities as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Location Filter -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Ubicación</label>
                    <select class="form-select form-select-sm" wire:model.live="filterLocation">
                        <option value="">(Todas)</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Estado</label>
                    <select class="form-select form-select-sm" wire:model.live="filterStatus">
                        <option value="">(Todos)</option>
                        <option value="good">Bueno</option>
                        <option value="bad">Malo</option>
                        <option value="warning">Regular</option>
                    </select>
                </div>
            </div>

            <!-- Third Party Filter -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Tercerizado</label>
                    <select class="form-select form-select-sm" wire:model.live="filterIsThirdParty">
                        <option value="">(Todos)</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <!-- Search -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">&nbsp;</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-2 pe-2"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 ps-0" placeholder="Buscar..." wire:model.live.debounce.300ms="search" style="font-size: 0.875rem;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr class="text-uppercase small text-muted">
                    <th>Código</th>
                    <th>Ciudad</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Última Auditoría</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spaces as $space)
                    <tr>
                        <td class="fw-bold text-dark">{{ $space->external_code }}</td>
                        <td>{{ $space->city }}</td>
                        <td>
                            <div class="text-dark fw-bold">{{ $space->location_name }}</div>
                            <small class="text-muted">{{ $space->location }}</small>
                        </td>
                        <td>
                            @if($space->latestAudit)
                                @php
                                    $status = $space->latestAudit->general_status;
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
                                        default => 'Sin Datos'
                                    };
                                @endphp
                                <span class="badge rounded-pill {{ $badgeClass }} px-3 py-2 fw-normal">
                                    {{ $statusText }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill px-3 py-2 fw-normal">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($space->latestAudit)
                                <span class="d-block text-dark">{{ $space->latestAudit->audit_date->format('d M, Y') }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('platform.spaces.view', $space->id) }}" class="btn btn-sm btn-link text-dark p-0" title="Ver Detalles" data-turbo="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                      <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                      <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
                            No se encontraron espacios con los filtros actuales.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 border-top pt-3">
        {{ $spaces->links() }}
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Restore scroll position after Livewire updates
            Livewire.hook('morph.updated', ({ el, component }) => {
                const container = document.querySelector('.space-browser-container');
                if (container) {
                     // logic to maintain scroll if needed, but often automatic
                }
            });
        });
    </script>
@endpush
