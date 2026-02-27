<div>
    <!-- Date Range Filter -->
    <div class="bg-white rounded shadow-sm p-3 mb-4 d-flex align-items-center gap-3 flex-wrap">
        <span class="text-muted small text-uppercase font-weight-bold text-nowrap">Filtrar por fecha:</span>
        <div class="d-flex align-items-center gap-2">
            <label for="dateFrom" class="text-muted small mb-0 text-nowrap">Desde</label>
            <input type="date" id="dateFrom" wire:model="dateFrom" class="form-control form-control-sm" style="width: auto;">
        </div>
        <div class="d-flex align-items-center gap-2">
            <label for="dateTo" class="text-muted small mb-0 text-nowrap">Hasta</label>
            <input type="date" id="dateTo" wire:model="dateTo" class="form-control form-control-sm" style="width: auto;">
        </div>
        <button wire:click="filter" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
            <i class="bs.funnel"></i> Filtrar
        </button>
        @unless($isDefaultWeek)
            <button wire:click="resetDates" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bs.arrow-counterclockwise"></i> Semana actual
            </button>
        @endunless
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
                            <h6 class="text-muted small text-uppercase font-weight-bold mb-1">{{ $metric['label'] }}</h6>
                            <h3 class="mb-0 {{ $metric['color'] === 'danger' ? 'text-danger' : ($metric['color'] === 'warning' ? 'text-warning' : '') }}">
                                {{ $metric['value'] }}
                            </h3>
                            <small class="text-muted">{{ $metric['subtext'] }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Recent Audits Table -->
    <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-muted small text-uppercase font-weight-bold">Auditorías del Período</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                    <tr>
                        <th class="px-4 py-3">Espacio</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3 text-center">Semana</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-right">Fecha</th>
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
                <h6 class="text-muted small text-uppercase font-weight-bold mb-3">Criterios con más fallas</h6>
                @if($criteriaFailures->isNotEmpty())
                    @php $maxFails = $criteriaFailures->max('total'); @endphp
                    @foreach($criteriaFailures as $criterion)
                        <div class="d-flex align-items-center mb-2">
                            <span class="small text-nowrap me-3" style="min-width: 100px;">{{ $criterion->name }}</span>
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 20px;">
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
                <h6 class="text-muted small text-uppercase font-weight-bold mb-3">Mantenimientos por estado</h6>
                @php
                    $statusConfig = [
                        \App\Models\Maintenance::STATUS_REPORTED => ['label' => 'Reportado', 'color' => '#ffc107'],
                        \App\Models\Maintenance::STATUS_PENDING_ADVISUAL => ['label' => 'Pendiente Advisual', 'color' => '#0dcaf0'],
                        \App\Models\Maintenance::STATUS_IN_PROGRESS => ['label' => 'En Progreso', 'color' => '#0d6efd'],
                        \App\Models\Maintenance::STATUS_CLOSED => ['label' => 'Cerrado', 'color' => '#198754'],
                    ];
                    $totalMaint = $maintByStatus->sum();
                @endphp
                @if($totalMaint > 0)
                    <div class="d-flex flex-wrap gap-3 mb-3 justify-content-center">
                        @foreach($statusConfig as $status => $config)
                            @php $count = $maintByStatus[$status] ?? 0; @endphp
                            <div class="text-center">
                                <div class="fs-3 fw-bold" style="color: {{ $config['color'] }};">{{ $count }}</div>
                                <small class="text-muted">{{ $config['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    {{-- Stacked bar --}}
                    <div class="progress" style="height: 30px;">
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

    <!-- Top Espacios con Errores -->
    @if($topBadSpaces->isNotEmpty())
        <div class="bg-white rounded shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-bottom">
                <h6 class="mb-0 text-muted small text-uppercase font-weight-bold">Top espacios con errores</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                        <tr>
                            <th class="px-4 py-2">#</th>
                            <th class="px-4 py-2">Código</th>
                            <th class="px-4 py-2">Ciudad</th>
                            <th class="px-4 py-2">Tipo</th>
                            <th class="px-4 py-2 text-center">Auditorías Malas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topBadSpaces as $i => $space)
                            <tr>
                                <td class="px-4 py-2 text-muted">{{ $i + 1 }}</td>
                                <td class="px-4 py-2 fw-bold text-danger">{{ $space['code'] }}</td>
                                <td class="px-4 py-2">{{ $space['city'] }}</td>
                                <td class="px-4 py-2">{{ $space['type'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="badge bg-danger">{{ $space['total'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .bg-primary-soft {
            background-color: rgba(var(--orchid-primary-rgb), 0.1);
        }
    </style>
</div>
