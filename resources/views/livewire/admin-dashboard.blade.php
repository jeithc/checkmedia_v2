<div wire:poll.30s>
    @include('orchid.partials.product-ui-styles')

    @php
        $productUnits = collect(\App\Models\AdvertisingSpace::BUSINESS_UNITS)
            ->mapWithKeys(fn ($value) => [$value => \App\Models\AdvertisingSpace::businessUnitMeta($value)]);
    @endphp

    <!-- Filtros del Dashboard (form GET: los charts Orchid leen la URL) -->
    <div class="bg-white rounded shadow-sm p-3 mb-4">
        <div class="product-filter-bar mb-3">
            <span class="pf-label">Producto</span>

            <a href="{{ request()->fullUrlWithQuery(['producto' => null]) }}"
                class="pf-chip {{ $producto ? '' : 'active' }}">
                Todos
            </a>

            @foreach($productUnits as $value => $unit)
                <a href="{{ request()->fullUrlWithQuery(['producto' => $value]) }}"
                    class="pf-chip {{ $producto === $value ? 'active' : '' }}"
                    title="{{ $value }}">
                    <span class="pf-dot {{ $unit['hollow'] ? 'hollow' : '' }}" style="--pf-dot-color: {{ $unit['color'] }}"></span>
                    {{ $unit['label'] }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('platform.main') }}"
            class="d-flex flex-wrap align-items-center gap-2">
            @if($producto)
                <input type="hidden" name="producto" value="{{ $producto }}">
            @endif

            <label class="pf-select">
                <span>Ciudad</span>
                <select name="city" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($filterOptions['cities'] as $value => $label)
                        <option value="{{ $value }}" @selected($city === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pf-select">
                <span>Categoría</span>
                <select name="category" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($filterOptions['categories'] as $value => $label)
                        <option value="{{ $value }}" @selected($category === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pf-select">
                <span>Tipo mant.</span>
                <select name="maintenanceType" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($filterOptions['maintenanceTypes'] as $value => $label)
                        <option value="{{ $value }}" @selected($maintenanceType === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pf-select">
                <span>Estado</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($filterOptions['auditStatuses'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pf-select">
                <span>Desde</span>
                <input type="date" name="from" value="{{ $dateFrom }}" onchange="this.form.submit()"
                    style="border:0;background:transparent;font-size:.8125rem;font-weight:500;color:#212529;outline:none;">
            </label>

            <label class="pf-select">
                <span>Hasta</span>
                <input type="date" name="to" value="{{ $dateTo }}" onchange="this.form.submit()"
                    style="border:0;background:transparent;font-size:.8125rem;font-weight:500;color:#212529;outline:none;">
            </label>

            <label class="pf-search ms-auto">
                <i class="bi bi-search"></i>
                <input type="text" name="externalCode" value="{{ $externalCode }}"
                    placeholder="Código espacio…">
            </label>

            @if(!$isDefaultWeek)
                <a href="{{ route('platform.main') }}" class="pf-chip" title="Limpiar filtros">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Metrics Section -->
    <div class="row mb-4 g-3">
        @foreach($metrics as $id => $metric)
            @php
                $valueColor = match($metric['color'] ?? 'neutral') {
                    'danger' => '#991b1b',
                    'warning' => '#92400e',
                    'success' => '#065f46',
                    default => '#1f2937',
                };
                $tag = isset($metric['href']) ? 'a' : 'div';
            @endphp
            <div class="col-sm-6 col-lg-3">
                <{{ $tag }} @if(isset($metric['href'])) href="{{ $metric['href'] }}" @endif
                    class="bg-white rounded shadow-sm p-4 h-100 d-block text-decoration-none dash-card">
                    <h6 class="pf-label d-block mb-2">{{ $metric['label'] }}</h6>
                    <div class="fs-3 fw-bold mb-0 mnt-num" style="color: {{ $valueColor }};">
                        {{ $metric['value'] }}
                    </div>
                    <small class="mnt-muted">{{ $metric['subtext'] }}</small>
                </{{ $tag }}>
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

        <!-- Calidad del Período (cumplimiento + distribución good/bad) -->
        <div class="col-lg-4">
            <div class="bg-white rounded shadow-sm p-4 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Calidad del Período</h6>
                <div class="text-center py-2">
                    @if($kpis['compliance_rate'] !== null)
                        @php
                            $complianceColor = match(true) {
                                $kpis['compliance_rate'] >= 90 => '#065f46',
                                $kpis['compliance_rate'] >= 70 => '#1e40af',
                                $kpis['compliance_rate'] >= 50 => '#92400e',
                                default => '#991b1b',
                            };
                            $badPct = 100 - $kpis['compliance_rate'];
                        @endphp
                        <div class="fs-1 fw-bold mnt-num" style="color: {{ $complianceColor }};">{{ $kpis['compliance_rate'] }}%</div>
                        <div class="text-muted">auditorías sin novedad</div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar" style="width: {{ $kpis['compliance_rate'] }}%; background: #059669;"
                                title="Sin novedad: {{ $kpis['good_audits'] }}"></div>
                            <div class="progress-bar" style="width: {{ $badPct }}%; background: #dc2626;"
                                title="Con errores: {{ $kpis['bad_audits'] }}"></div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <span style="color:#065f46;">● {{ $kpis['good_audits'] }} sin novedad</span>
                            <span class="ms-2" style="color:#991b1b;">● {{ $kpis['bad_audits'] }} con errores</span>
                            <span class="ms-2">de {{ $kpis['total_audits'] }}</span>
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

    <!-- Pendientes por solicitar mantenimiento (lo más accionable: arriba) -->
    <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="text-muted small text-uppercase fw-bold mb-0">
                Pendientes por solicitar mantenimiento
                @if($pendingTotal > 0)
                    <span class="mnt-badge mnt-badge--media ms-2">{{ $pendingTotal }}</span>
                @endif
            </h6>
            @if($pendingTotal > count($pendingRequisitions))
                <small class="text-muted">Mostrando {{ count($pendingRequisitions) }} de {{ $pendingTotal }}</small>
            @endif
        </div>
        @if(count($pendingRequisitions) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Auditoría</th>
                            <th>Espacio</th>
                            <th>Ciudad</th>
                            <th>Criterios pendientes</th>
                            <th>Fecha auditoría</th>
                            <th class="text-end pe-4">Días en espera</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingRequisitions as $row)
                            <tr>
                                <td class="px-4 fw-bold">
                                    <a href="{{ route('platform.audit.detail', $row['audit_id']) }}" class="text-decoration-none">
                                        #{{ $row['audit_id'] }}
                                    </a>
                                </td>
                                <td><span class="mnt-code">{{ $row['space_code'] }}</span></td>
                                <td>{{ $row['city'] }}</td>
                                <td><span class="small" style="color:#991b1b;">{{ $row['criteria'] }}</span></td>
                                <td><small class="text-muted mnt-num">{{ optional($row['audit_date'])->format('d/m/Y') ?? '—' }}</small></td>
                                <td class="text-end pe-4">
                                    @php $days = $row['days_waiting'] ?? 0; @endphp
                                    <span class="mnt-badge {{ $days >= 7 ? 'mnt-badge--alta' : ($days >= 3 ? 'mnt-badge--media' : 'mnt-badge--none') }}">{{ $days }} d</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center text-muted py-4">
                <p class="mb-0">Sin auditorías pendientes por solicitar mantenimiento en el período.</p>
            </div>
        @endif
    </div>

    <!-- Recent Audits Table -->
    <div id="auditorias-periodo" class="bg-white rounded shadow-sm overflow-hidden mb-4">
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
                            $isResolved = $audit->resolved_at !== null;
                            $unitMeta = $audit->space?->business_unit
                                ? \App\Models\AdvertisingSpace::businessUnitMeta($audit->space->business_unit)
                                : null;
                        @endphp
                        <tr class="align-middle {{ $isResolved ? 'opacity-75' : '' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.audit.detail', $audit) }}" class="text-decoration-none">
                                    <span class="mnt-code">{{ $audit->space->external_code }}</span>
                                </a>
                                @if($unitMeta)
                                    <div class="mnt-product small mnt-muted" title="{{ $audit->space->business_unit }}">
                                        <span class="mnt-dot {{ $unitMeta['hollow'] ? 'hollow' : '' }}" style="--mnt-dot-color: {{ $unitMeta['color'] }}"></span>
                                        {{ $unitMeta['label'] }}
                                    </div>
                                @endif
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
                                    <span class="mnt-badge mnt-badge--good">Bueno</span>
                                @else
                                    <span class="mnt-badge mnt-badge--bad">Malo</span>
                                @endif
                                @if($isResolved)
                                    <div class="text-muted small mt-1">Resuelto</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end text-muted small mnt-num">
                                {{ $audit->audit_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('platform.audit.detail', $audit) }}" class="btn btn-sm btn-link text-muted">
                                    <i class="bi bi-chevron-right"></i>
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
                        \App\Models\Maintenance::STATUS_REPORTED => ['label' => 'Reportado', 'color' => '#6b7280'],
                        \App\Models\Maintenance::STATUS_PENDING_ADVISUAL => ['label' => 'Pendiente Advisual', 'color' => '#d97706'],
                        \App\Models\Maintenance::STATUS_IN_PROGRESS => ['label' => 'En Progreso', 'color' => '#2563eb'],
                        \App\Models\Maintenance::STATUS_CLOSED => ['label' => 'Cerrado', 'color' => '#059669'],
                    ];
                    $totalMaint = $maintByStatus->sum();
                @endphp
                @if($totalMaint > 0)
                    <div class="d-flex flex-wrap gap-3 mb-3 justify-content-center">
                        @foreach($statusConfig as $status => $config)
                            @php $count = $maintByStatus[$status] ?? 0; @endphp
                            <div class="text-center">
                                <div class="fs-3 fw-bold mnt-num" style="color: {{ $count > 0 ? $config['color'] : '#c6cbd2' }};">{{ $count }}</div>
                                <small class="text-muted">{{ $config['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    {{-- Stacked bar --}}
                    <div class="progress" style="height: 12px;">
                        @foreach($statusConfig as $status => $config)
                            @php
                                $count = $maintByStatus[$status] ?? 0;
                                $pct = round(($count / $totalMaint) * 100, 1);
                            @endphp
                            @if($count > 0)
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ $pct }}%; background-color: {{ $config['color'] }};"
                                     title="{{ $config['label'] }}: {{ $count }} ({{ $pct }}%)">
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
                                <td class="px-4 py-3"><span class="mnt-code">{{ $space['code'] }}</span></td>
                                <td class="px-4 py-3">{{ $space['city'] }}</td>
                                <td class="px-4 py-3">{{ $space['type'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="mnt-badge mnt-badge--bad">{{ $space['total'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
