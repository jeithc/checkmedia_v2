@php
    // AuditDetailScreen requires audit.can_audit, but this screen only requires
    // maintenance.view (seeded role "auditor-estructural" has one and not the
    // other). Without this the whole row would be links to a 403.
    $canOpenAudit = auth()->user()?->hasAccess('audit.can_audit');
@endphp

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
                                @if($canOpenAudit)
                                    <a href="{{ route('platform.audit.detail', $audit->id) }}" class="text-decoration-none">#{{ $audit->id }}</a>
                                @else
                                    #{{ $audit->id }}
                                @endif
                            </td>
                            <td>{{ $audit->space?->external_code ?? '—' }}</td>
                            <td>{{ $audit->space?->city ?? '—' }}</td>
                            <td><span class="small text-danger">{{ $criteria }}</span></td>
                            <td><small class="text-muted">{{ optional($audit->audit_date)->format('d/m/Y') ?? '—' }}</small></td>
                            <td class="text-end">
                                <span class="mnt-badge {{ $days > 30 ? 'mnt-badge--bad' : 'mnt-badge--none' }}">{{ $days }} d</span>
                            </td>
                            <td class="text-end pe-4">
                                {{-- Same action pattern as /admin/spaces: a pill for the primary action
                                     plus the eye icon to view. The audit detail owns the request flow. --}}
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    @if($canOpenAudit)
                                        <a href="{{ route('platform.audit.detail', $audit->id) }}#solicitar"
                                            class="mnt-badge mnt-badge--bad text-decoration-none" title="Solicitar mantenimiento">
                                            Solicitar mantenimiento
                                        </a>
                                        <a href="{{ route('platform.audit.detail', $audit->id) }}" class="btn btn-sm btn-link mnt-row-view p-0" title="Ver auditoría" data-turbo="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                            </svg>
                                        </a>
                                    @else
                                        <span class="small text-muted">Sin permiso</span>
                                    @endif
                                </div>
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
