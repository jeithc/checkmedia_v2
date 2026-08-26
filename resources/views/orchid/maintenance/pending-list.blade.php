<div class="bg-white rounded shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="text-muted small text-uppercase fw-bold mb-0">
            Pendientes por solicitar mantenimiento
            @if($total > 0)<span class="badge bg-warning text-dark ms-2">{{ $total }}</span>@endif
        </h6>
        <small class="text-muted">Mostrando {{ $audits->firstItem() ?? 0 }}–{{ $audits->lastItem() ?? 0 }} de {{ $total }}</small>
    </div>

    @if($audits->count() > 0)
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">Auditoría</th>
                        <th>Espacio</th>
                        <th>Ciudad</th>
                        <th>Criterios pendientes</th>
                        <th>Fecha auditoría</th>
                        <th class="text-end">Días en espera</th>
                        <th class="text-end pe-4">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($audits as $audit)
                        @php
                            $days = $audit->audit_date ? (int) floor($audit->audit_date->diffInDays(now())) : 0;
                            $criteria = $audit->values->map(fn ($v) => $v->criterion?->name)->filter()->unique()->implode(', ');
                        @endphp
                        <tr>
                            <td class="px-4 fw-bold">
                                <a href="{{ route('platform.audit.detail', $audit->id) }}" class="text-decoration-none">#{{ $audit->id }}</a>
                            </td>
                            <td>{{ $audit->space?->external_code ?? '—' }}</td>
                            <td>{{ $audit->space?->city ?? '—' }}</td>
                            <td><span class="small text-danger">{{ $criteria }}</span></td>
                            <td><small class="text-muted">{{ optional($audit->audit_date)->format('d/m/Y') ?? '—' }}</small></td>
                            <td class="text-end">
                                <span class="badge {{ $days > 30 ? 'bg-danger' : 'bg-light text-dark border' }}">{{ $days }} d</span>
                            </td>
                            <td class="text-end pe-4">
                                {{-- The audit detail already owns the request flow (criterion picker + Advisual). --}}
                                <a href="{{ route('platform.audit.detail', $audit->id) }}#solicitar" class="btn btn-sm btn-danger">
                                    Solicitar mantenimiento
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">{{ $audits->links() }}</div>
    @else
        <div class="text-center text-muted py-4">
            <p class="mb-0">Sin auditorías pendientes por solicitar mantenimiento con estos filtros.</p>
        </div>
    @endif
</div>
