<div>
    <!-- Filtros del Dashboard -->
    <div class="bg-white rounded shadow-sm p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 text-muted small text-uppercase fw-bold">Filtros</h6>
            @if(!$isDefaultWeek)
                <button wire:click="resetFilters" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                </button>
            @endif
        </div>
        <div class="row g-2">
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Código espacio</label>
                <input type="text" wire:model.defer="externalCode" class="form-control form-control-sm" placeholder="Ej: AER-001">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Ciudad</label>
                <select wire:model.defer="city" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($filterOptions['cities'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Producto</label>
                <select wire:model.defer="producto" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($filterOptions['productos'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select wire:model.defer="category" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($filterOptions['categories'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Tipo mantenimiento</label>
                <select wire:model.defer="maintenanceType" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($filterOptions['maintenanceTypes'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select wire:model.defer="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($filterOptions['auditStatuses'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" wire:model.defer="dateFrom" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" wire:model.defer="dateTo" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 col-lg-2 d-flex align-items-end">
                <button wire:click="filter" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Metrics Section -->
    <div class="row mb-4 g-3">
        @foreach($metrics as $id => $metric)
            @php
                $borderColor = match($metric['color'] ?? 'primary') {
                    'danger' => 'border-danger',
                    'warning' => 'border-warning',
                    'success' => 'border-success',
                    default => 'border-primary'
                };
            @endphp
            <div class="col-sm-6 col-lg-3">
                <div class="bg-white rounded shadow-sm p-4 h-100 border-start {{ $borderColor }} border-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted small text-uppercase fw-bold mb-1">{{ $metric['label'] }}</h6>
                            <div class="fs-3 fw-bold mb-0 {{ $metric['color'] === 'danger' ? 'text-danger' : ($metric['color'] === 'warning' ? 'text-warning' : '') }}">
                                {{ $metric['value'] }}
                            </div>
                            <small class="text-muted">{{ $metric['subtext'] }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- KPIs Section -->
    <div class="row mb-4 g-3">
        <!-- Novedades Abiertas vs Cerradas -->
        <div class="col-lg-4">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Novedades Abiertas vs Cerradas</h6>
                @if($kpis['total_maintenances'] > 0)
                    <div class="d-flex justify-content-center gap-4 mb-3">
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-warning">{{ $kpis['open_maintenances'] }}</div>
                            <small class="text-muted">Abiertas</small>
                        </div>
                        <div class="d-flex align-items-center text-muted px-2">
                            <span class="fs-4">/</span>
                        </div>
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-success">{{ $kpis['closed_maintenances'] }}</div>
                            <small class="text-muted">Cerradas</small>
                        </div>
                    </div>
                    @php
                        $closedPct = round(($kpis['closed_maintenances'] / $kpis['total_maintenances']) * 100, 1);
                        $openPct = 100 - $closedPct;
                    @endphp
                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $closedPct }}%"
                             title="Cerradas: {{ $closedPct }}%">
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $openPct }}%"
                             title="Abiertas: {{ $openPct }}%">
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">{{ $kpis['total_maintenances'] }} total — {{ $closedPct }}% resueltas</small>
                    </div>
                @else
                    <div class="text-center text-muted py-3">
                        <p class="mb-0">Sin novedades registradas.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tiempo Promedio de Cierre -->
        <div class="col-lg-4">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Tiempo Promedio de Cierre</h6>
                <div class="text-center py-2">
                    @if($kpis['avg_closure_days'] !== null)
                        @php
                            $closureColor = match(true) {
                                $kpis['avg_closure_days'] <= 3 => 'text-success',
                                $kpis['avg_closure_days'] <= 7 => 'text-primary',
                                $kpis['avg_closure_days'] <= 14 => 'text-warning',
                                default => 'text-danger',
                            };
                        @endphp
                        <div class="fs-1 fw-bold {{ $closureColor }}">{{ $kpis['avg_closure_days'] }}</div>
                        <div class="text-muted">días promedio</div>
                        <small class="text-muted d-block mt-1">
                            Desde solicitud hasta cierre
                        </small>
                    @else
                        <div class="text-muted py-3">
                            <p class="mb-0">Sin datos de cierre aún.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tasa de Cumplimiento -->
        <div class="col-lg-4">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Tasa de Cumplimiento</h6>
                <div class="text-center py-2">
                    @if($kpis['compliance_rate'] !== null)
                        @php
                            $complianceColor = match(true) {
                                $kpis['compliance_rate'] >= 90 => 'text-success',
                                $kpis['compliance_rate'] >= 70 => 'text-primary',
                                $kpis['compliance_rate'] >= 50 => 'text-warning',
                                default => 'text-danger',
                            };
                        @endphp
                        <div class="fs-1 fw-bold {{ $complianceColor }}">{{ $kpis['compliance_rate'] }}%</div>
                        <div class="text-muted">auditorías sin novedad</div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar {{ str_replace('text-', 'bg-', $complianceColor) }}"
                                 role="progressbar" style="width: {{ $kpis['compliance_rate'] }}%">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            En el período seleccionado
                        </small>
                    @else
                        <div class="text-muted py-3">
                            <p class="mb-0">Sin auditorías en el período.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Audits Table -->
    <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-muted small text-uppercase fw-bold">Auditorías del Período</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase fw-bold">
                    <tr>
                        <th class="px-4 py-3">Espacio</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3 text-center">Semana</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-end">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAudits as $audit)
                        @php
                            $rowClass = $audit->general_status === 'bad' ? 'table-danger' : '';
                            $isResolved = $audit->resolved_at !== null;
                        @endphp
                        <tr class="align-middle {{ $rowClass }} {{ $isResolved ? 'opacity-75' : '' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="fw-bold {{ $audit->general_status === 'bad' ? 'text-danger' : 'text-primary' }}">
                                    {{ $audit->space->external_code }}
                                </a>
                                <div class="text-muted small">{{ $audit->space->category ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-dark">{{ $audit->space->type }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="bg-light px-2 py-1 rounded small font-monospace">
                                    S{{ $audit->week }} / {{ $audit->year }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($audit->general_status === 'good')
                                    <span class="badge bg-success small">Bueno</span>
                                @else
                                    <span class="badge bg-danger small">Malo</span>
                                @endif
                                @if($isResolved)
                                    <div class="text-muted small mt-1">Resuelto</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end text-muted small">
                                {{ $audit->audit_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="btn btn-sm btn-link {{ $audit->general_status === 'bad' ? 'text-danger' : 'text-muted' }}">
                                    <i class="bs.chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-5 text-center text-muted fst-italic">
                                No se encontraron auditorías en este período.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4 g-3">
        <!-- Criterios con más fallas -->
        <div class="col-lg-6">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Criterios con más fallas</h6>
                @if($criteriaFailures->isNotEmpty())
                    @php $maxFails = $criteriaFailures->max('total'); @endphp
                    @foreach($criteriaFailures as $criterion)
                        <div class="d-flex align-items-center mb-2">
                            <span class="small text-nowrap me-3 w-25">{{ $criterion->name }}</span>
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 24px;">
                                    <div class="progress-bar bg-danger" role="progressbar"
                                         style="width: {{ $maxFails > 0 ? round(($criterion->total / $maxFails) * 100) : 0 }}%">
                                        {{ $criterion->total }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">Sin fallas en el período.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Mantenimientos por estado -->
        <div class="col-lg-6">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Mantenimientos por estado</h6>
                @php
                    $statusConfig = [
                        \App\Models\Maintenance::STATUS_REPORTED => ['label' => 'Reportado', 'color' => 'var(--bs-warning)', 'class' => 'text-warning'],
                        \App\Models\Maintenance::STATUS_PENDING_ADVISUAL => ['label' => 'Pendiente Advisual', 'color' => 'var(--bs-info)', 'class' => 'text-info'],
                        \App\Models\Maintenance::STATUS_IN_PROGRESS => ['label' => 'En Progreso', 'color' => 'var(--bs-primary)', 'class' => 'text-primary'],
                        \App\Models\Maintenance::STATUS_CLOSED => ['label' => 'Cerrado', 'color' => 'var(--bs-success)', 'class' => 'text-success'],
                    ];
                    $totalMaint = $maintByStatus->sum();
                @endphp
                @if($totalMaint > 0)
                    <div class="d-flex flex-wrap gap-3 mb-3 justify-content-center">
                        @foreach($statusConfig as $status => $config)
                            @php $count = $maintByStatus[$status] ?? 0; @endphp
                            <div class="text-center">
                                <div class="fs-3 fw-bold {{ $config['class'] }}">{{ $count }}</div>
                                <small class="text-muted">{{ $config['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    {{-- Stacked bar --}}
                    <div class="progress" style="height: 24px;">
                        @foreach($statusConfig as $status => $config)
                            @php
                                $count = $maintByStatus[$status] ?? 0;
                                $pct = round(($count / $totalMaint) * 100, 1);
                            @endphp
                            @if($count > 0)
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ $pct }}%; background-color: {{ $config['color'] }};"
                                     title="{{ $config['label'] }}: {{ $count }}">
                                    {{ $pct }}%
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">Sin mantenimientos registrados.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Listado de Auditorías -->
    <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-muted small text-uppercase fw-bold">Auditorías</h6>
            <small class="text-muted">{{ $audits->total() }} resultados</small>
        </div>
        <div class="table-responsive" wire:loading.class="opacity-50">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase fw-bold">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Ciudad</th>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Auditor</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                        <tr>
                            <td class="px-4 py-3 fw-bold">{{ $audit->space->external_code ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $audit->space->city ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $audit->space->type ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="badge bg-{{ $audit->audit_type === 'structural' ? 'warning' : 'primary' }}">
                                    {{ ['general' => 'General', 'structural' => 'Estructural'][$audit->audit_type] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge bg-{{ $audit->general_status === 'bad' ? 'danger' : 'success' }}">
                                    {{ $audit->general_status === 'bad' ? 'Malo' : 'Bueno' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $audit->user->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ optional($audit->audit_date)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('platform.audit.detail', $audit) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Sin auditorías para los filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($audits->hasPages())
            <div class="px-4 py-3 border-top">
                {{ $audits->links() }}
            </div>
        @endif
    </div>

    <!-- Top Espacios con Errores -->
    @if($topBadSpaces->isNotEmpty())
        <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-bottom">
                <h6 class="mb-0 text-muted small text-uppercase fw-bold">Top espacios con errores</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Ciudad</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-center">Auditorías Malas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topBadSpaces as $i => $space)
                            <tr>
                                <td class="px-4 py-3 text-muted">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 fw-bold text-danger">{{ $space['code'] }}</td>
                                <td class="px-4 py-3">{{ $space['city'] }}</td>
                                <td class="px-4 py-3">{{ $space['type'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-danger">{{ $space['total'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
