<div class="row">
    <!-- Header -->
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3 d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center me-4 mb-2">
                <div class="bg-light rounded p-2 me-3 text-center" style="min-width: 60px;">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">LOTE</small>
                    <span class="fs-5 fw-bold text-dark">#{{ $batch->id }}</span>
                </div>
                <div>
                    <h5 class="mb-0 text-dark">
                        {{ $batch->name }}
                        @if($batch->isCancelled())
                            <span class="badge bg-danger ms-2">Cancelado</span>
                        @endif
                    </h5>
                    <small class="text-muted d-block">
                        {{ $batch->city ?: 'Sin ciudad' }} &middot;
                        Creado por {{ $batch->createdBy?->name ?? 'N/A' }} el {{ $batch->created_at->format('d/m/Y H:i') }}
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">REQUISICIÓN ADVISUAL</small>
                    @if($batch->advisual_requisition_id)
                        <span class="badge bg-primary fs-6">{{ $batch->advisual_requisition_id }}</span>
                    @else
                        <span class="badge bg-secondary fs-6">Sin enviar</span>
                    @endif
                </div>
                <div class="text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">SINCRONIZADO</small>
                    <span class="text-dark">{{ $batch->advisual_synced_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    @if($batch->isCancelled())
        <div class="col-12 mb-3">
            <div class="alert alert-warning mb-0">
                <strong>Lote cancelado</strong> por {{ $batch->cancelledBy?->name ?? 'N/A' }}
                el {{ $batch->cancelled_at->format('d/m/Y H:i') }}.
                @if($batch->advisual_requisition_id)
                    La requisición {{ $batch->advisual_requisition_id }} quedó anulada en Advisual.
                @endif
            </div>
        </div>
    @endif

    @if($batch->advisual_sync_error)
        <div class="col-12 mb-3">
            <div class="alert alert-danger mb-0">
                <strong>Error de sincronización con Advisual:</strong> {{ $batch->advisual_sync_error }}
            </div>
        </div>
    @endif

    <!-- Totals -->
    <div class="col-md-4 mb-3">
        <div class="bg-white rounded shadow-sm p-3 text-center h-100">
            <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">VALLAS</small>
            <span class="fs-4 fw-bold text-dark">{{ $batch->spaces_count }}</span>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="bg-white rounded shadow-sm p-3 text-center h-100">
            <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">CON ORDEN DE COMPRA</small>
            <span class="fs-4 fw-bold text-dark">{{ $batch->with_po_count }}</span>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="bg-white rounded shadow-sm p-3 text-center h-100">
            <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">COSTO TOTAL</small>
            <span class="fs-4 fw-bold text-dark">$ {{ number_format($batch->total_cost, 0, ',', '.') }}</span>
        </div>
    </div>

    <!-- Maintenances -->
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3">Vallas del Lote</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase" style="font-size: 0.7rem;">
                            <th>Línea</th>
                            <th>Código</th>
                            <th>Ubicación</th>
                            <th>Ciudad</th>
                            <th>Estado</th>
                            <th>OC</th>
                            <th class="text-end">Costo</th>
                            <th class="text-center">Mant.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($maintenances as $maintenance)
                            <tr>
                                <td>{{ $maintenance->advisual_requisition_line ?? '-' }}</td>
                                <td class="fw-bold">{{ $maintenance->advertisingSpace?->external_code ?? 'N/A' }}</td>
                                <td>{{ $maintenance->advertisingSpace?->location_name ?? '-' }}</td>
                                <td>{{ $maintenance->advertisingSpace?->city ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $maintenance->status_color }}">{{ $maintenance->status_label }}</span>
                                </td>
                                <td>{{ $maintenance->advisual_purchase_order_id ?? '-' }}</td>
                                <td class="text-end">
                                    @if($maintenance->advisual_purchase_order_total)
                                        $ {{ number_format($maintenance->advisual_purchase_order_total, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('platform.maintenances.detail', $maintenance->id) }}" class="btn btn-sm btn-link p-0">
                                        #{{ $maintenance->id }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">Este lote no tiene mantenimientos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
