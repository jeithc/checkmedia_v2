@include('orchid.partials.product-ui-styles')

@push('head')
    <style>
        .input-group-sm .input-group-text, .input-group-sm .form-control {
            min-height: 31px; /* Force consistent height for sm inputs */
        }

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

        /* Pagination active state */
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white !important;
        }
        .page-link {
            color: #6b7280;
        }
        .page-link:hover {
            background-color: #f3f4f6;
        }
    </style>
@endpush

@php
    $productUnits = collect(\App\Models\AdvertisingSpace::BUSINESS_UNITS)
        ->mapWithKeys(fn ($value) => [$value => \App\Models\AdvertisingSpace::businessUnitMeta($value)]);
@endphp

<div class="space-browser-container p-4 bg-white rounded shadow-sm">
    <!-- Product Chips -->
    <div class="product-filter-bar mb-4">
        <span class="pf-label">Producto</span>

        <button type="button" wire:click="setProduct()"
            class="pf-chip {{ $filterProduct ? '' : 'active' }}">
            Todos
        </button>

        @foreach($productUnits as $value => $unit)
            <button type="button" wire:click="setProduct('{{ $value }}')"
                class="pf-chip {{ $filterProduct === $value ? 'active' : '' }}"
                title="{{ $value }}">
                <span class="pf-dot {{ $unit['hollow'] ? 'hollow' : '' }}" style="--pf-dot-color: {{ $unit['color'] }}"></span>
                {{ $unit['label'] }}
            </button>
        @endforeach
    </div>

    <!-- Filters Toolbar -->
    <div class="filter-toolbar mb-4">
        <div class="row g-3">
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

            <!-- Search -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="filter-group">
                    <label class="small text-muted fw-bold mb-1 d-block">&nbsp;</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-2 pe-2"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 ps-0" placeholder="Buscar código, ciudad o ubicación..." wire:model.live.debounce.300ms="search" style="font-size: 0.875rem;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Ciudad</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Última Auditoría</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spaces as $space)
                    @php $unitMeta = $space->business_unit ? \App\Models\AdvertisingSpace::businessUnitMeta($space->business_unit) : null; @endphp
                    <tr>
                        <td><span class="mnt-code">{{ $space->external_code }}</span></td>
                        <td>
                            @if($unitMeta)
                                <span class="mnt-product" title="{{ $space->business_unit }}">
                                    <span class="mnt-dot {{ $unitMeta['hollow'] ? 'hollow' : '' }}" style="--mnt-dot-color: {{ $unitMeta['color'] }}"></span>
                                    {{ $unitMeta['label'] }}
                                </span>
                            @else
                                <span class="mnt-product mnt-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $space->city }}</td>
                        <td style="white-space: normal;">
                            <div class="text-dark fw-semibold">{{ $space->location_name }}</div>
                            <small class="mnt-muted">{{ $space->location }}</small>
                        </td>
                        <td>
                            @if($space->latestAudit)
                                @php
                                    $status = $space->latestAudit->general_status;
                                    $statusText = match($status) {
                                        'good' => 'Bueno',
                                        'bad' => 'Malo',
                                        'warning' => 'Regular',
                                        default => 'Sin Datos'
                                    };
                                @endphp
                                <span class="mnt-badge mnt-badge--{{ $status }}">{{ $statusText }}</span>
                            @else
                                <span class="mnt-badge mnt-badge--none">Sin auditar</span>
                            @endif
                        </td>
                        <td>
                            @if($space->latestAudit)
                                <span class="mnt-num">{{ $space->latestAudit->audit_date->format('d/m/Y') }}</span>
                            @else
                                <span class="mnt-muted">—</span>
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
                        <td colspan="7" class="text-center py-5 text-muted">
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
