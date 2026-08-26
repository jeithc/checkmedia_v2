{{-- OCs por valoración + actividad mensual. Reemplaza pie y charts de un solo punto. --}}
@include('orchid.partials.product-ui-styles')

@php
    $po = $po_value_summary ?? ['with_value' => 0, 'without_value' => 0, 'no_oc' => 0, 'total' => 0];
    $months = $monthly_activity ?? [];

    $poSegments = [
        ['label' => 'Con valor', 'count' => $po['with_value'], 'color' => '#059669'],
        ['label' => 'Sin valor', 'count' => $po['without_value'], 'color' => '#d97706'],
        ['label' => 'Sin OC', 'count' => $po['no_oc'], 'color' => '#9ca3af'],
    ];
@endphp

<div class="row g-3 mb-3">
    @if($po['total'] > 0)
    <div class="col-lg-6">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h6 class="text-muted small text-uppercase fw-bold mb-1">OCs por Valoración</h6>
            <p class="text-muted small mb-3">Mantenimientos correctivos con requisición en el período.</p>

            <div class="progress mb-3" style="height: 12px;">
                @foreach($poSegments as $seg)
                    @if($seg['count'] > 0)
                        <div class="progress-bar" style="width: {{ round($seg['count'] / $po['total'] * 100, 1) }}%; background: {{ $seg['color'] }};"
                            title="{{ $seg['label'] }}: {{ $seg['count'] }}"></div>
                    @endif
                @endforeach
            </div>

            <div class="d-flex flex-wrap gap-4">
                @foreach($poSegments as $seg)
                    <div>
                        <div class="fs-4 fw-bold mnt-num" style="color: {{ $seg['count'] > 0 ? $seg['color'] : '#c6cbd2' }};">{{ $seg['count'] }}</div>
                        <small class="text-muted">{{ $seg['label'] }} · {{ $po['total'] > 0 ? round($seg['count'] / $po['total'] * 100, 1) : 0 }}%</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if(count($months) > 0)
    <div class="col-lg-6">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h6 class="text-muted small text-uppercase fw-bold mb-1">Actividad Mensual</h6>
            <p class="text-muted small mb-3">Elementos auditados y costo ejecutado de OCs por mes.</p>

            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th>Mes</th>
                            <th class="text-end">Auditorías</th>
                            <th class="text-end">Costo OC (M COP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($months as $row)
                            <tr>
                                <td class="mnt-num">{{ $row['month'] }}</td>
                                <td class="text-end mnt-num fw-semibold">{{ number_format($row['audits']) }}</td>
                                <td class="text-end mnt-num">{{ $row['po_cost'] > 0 ? number_format($row['po_cost'], 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
