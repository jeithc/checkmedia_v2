<div wire:poll.10s>
    <!-- Metrics Section -->
    <div class="row mb-4 g-3">
        @foreach($metrics as $id => $metric)
            <div class="col-sm-6 col-lg-3">
                <div class="bg-white rounded shadow-sm p-4 h-100 border-start border-primary border-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted small text-uppercase font-weight-bold mb-1">{{ $metric['label'] }}</h6>
                            <h3 class="mb-0">{{ $metric['value'] }}</h3>
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
            <h5 class="mb-0 font-weight-bold">Últimas Auditorías</h5>
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
                        <tr class="align-middle transition-colors hover:bg-light/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="text-primary font-weight-bold">
                                    {{ $audit->space->external_code }}
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
                                    <span class="text-success small d-inline-flex align-items-center">
                                        <span class="me-1">●</span> Bueno
                                    </span>
                                @else
                                    <span class="text-danger small d-inline-flex align-items-center">
                                        <span class="me-1">●</span> Malo
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-muted small">
                                {{ $audit->audit_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('platform.audit.detail', $audit) }}"
                                    class="btn btn-sm btn-link text-muted">
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