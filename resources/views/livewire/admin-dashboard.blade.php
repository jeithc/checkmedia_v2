<div wire:poll.10s>
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
        <div class="px-4 py-3 border-b border-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0 font-weight-bold">Auditorías de la Semana Actual</h5>
            <span class="badge bg-primary-soft text-primary uppercase small">Actualizado en vivo</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                    <tr>
                        <th class="px-4 py-3">Espacio</th>
                        <th class="px-4 py-3 text-center">Semana</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-right">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light">
                    @forelse($recentAudits as $audit)
                        @php
                            $rowClass = match($audit->general_status) {
                                'bad' => 'table-danger',
                                'acceptable' => 'table-warning',
                                default => ''
                            };
                            $isResolved = $audit->resolved_at !== null;
                        @endphp
                        <tr class="align-middle transition-colors hover:bg-light/50 {{ $rowClass }} {{ $isResolved ? 'opacity-75' : '' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="font-weight-bold {{ $audit->general_status === 'bad' ? 'text-danger' : ($audit->general_status === 'acceptable' ? 'text-warning' : 'text-primary') }}">
                                    {{ $audit->space->external_code }}
                                    @if($audit->general_status === 'bad' && !$isResolved)
                                        <span class="badge bg-danger ms-2">URGENTE</span>
                                    @endif
                                </a>
                                <div class="text-muted small">{{ $audit->space->type }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="bg-light px-2 py-1 rounded text-xs font-mono">
                                    S{{ $audit->week }} / {{ $audit->year }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($audit->general_status === 'good')
                                    <span class="badge bg-success small">
                                        <i class="bs.check-circle me-1"></i> Bueno
                                    </span>
                                @elseif($audit->general_status === 'acceptable')
                                    <span class="badge bg-warning text-dark small">
                                        <i class="bs.exclamation-triangle me-1"></i> Aceptable
                                    </span>
                                @else
                                    <span class="badge bg-danger small">
                                        <i class="bs.x-circle me-1"></i> Malo
                                    </span>
                                @endif
                                @if($isResolved)
                                    <div class="text-muted small mt-1">
                                        <i class="bs.check2"></i> Resuelto
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-muted small">
                                {{ $audit->audit_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="btn btn-sm btn-link {{ $audit->general_status === 'bad' ? 'text-danger' : ($audit->general_status === 'acceptable' ? 'text-warning' : 'text-muted') }}">
                                    <i class="bs.chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-5 text-center text-muted italic">
                                No se encontraron auditorías recientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <style>
        .bg-primary-soft {
            background-color: rgba(var(--orchid-primary-rgb), 0.1);
        }

        .transition-colors {
            transition: background-color 0.2s;
        }
    </style>
</div>