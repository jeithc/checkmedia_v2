@push('head')
    <link rel="stylesheet" href="{{ asset('resources/css/components/space-browser.css') }}">
@endpush

<div class="space-browser-container p-4 bg-white rounded shadow-sm">
    <!-- Filters Toolbar -->
    <div class="filter-toolbar mb-4">
        <div class="row g-3">
            <!-- Category Filter -->
            <div class="col-md-2">
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
            <div class="col-md-2">
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
            <div class="col-md-3">
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
            <div class="col-md-2">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">Estado</label>
                    <select class="form-select form-select-sm" wire:model.live="filterStatus">
                        <option value="">(Todos)</option>
                        <option value="good">Buen Estado</option>
                        <option value="bad">Con Novedad</option>
                        <option value="warning">Regular</option>
                    </select>
                </div>
            </div>

            <!-- Search -->
            <div class="col-md-3">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">&nbsp;</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" placeholder="Buscar..." wire:model.live.debounce.300ms="search">
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
                                        'good' => 'Buen Estado',
                                        'bad' => 'Con Novedad',
                                        'warning' => 'Regular',
                                        default => 'Sin Datos'
                                    };
                                @endphp
                                <span class="badge rounded-pill {{ $badgeClass }} px-3 py-2 fw-normal">
                                    {{ $statusText }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill px-3 py-2 fw-normal">Sin Auditoría</span>
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
                                <a href="{{ route('platform.spaces.view', $space->id) }}" class="btn btn-sm btn-light border text-secondary" title="Ver Detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- Future actions -->
                                <!-- 
                                <button class="btn btn-sm btn-light border text-secondary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button> 
                                -->
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
    <script type="module" src="{{ asset('resources/js/components/space-browser.js') }}"></script>
@endpush
