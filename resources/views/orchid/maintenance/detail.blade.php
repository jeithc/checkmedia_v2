<div class="row">
    <!-- Header -->
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3 d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center me-4 mb-2">
                <div class="bg-light rounded p-2 me-3 text-center" style="min-width: 60px;">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">MANT.</small>
                    <span class="fs-5 fw-bold text-dark">#{{ $maintenance->id }}</span>
                </div>
                <div>
                    <h5 class="mb-0 text-dark">{{ $maintenance->advertisingSpace?->external_code ?? 'N/A' }}</h5>
                    <small class="text-muted d-block">{{ $maintenance->advertisingSpace?->city ?? '' }} - {{ $maintenance->advertisingSpace?->location_name ?? '' }}</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">ESTADO</small>
                    <span class="badge bg-{{ $maintenance->status_color }} fs-6">{{ $maintenance->status_label }}</span>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">PRIORIDAD</small>
                    @php
                        $prioColor = match($maintenance->priority) { 'alta' => 'danger', 'media' => 'warning', 'baja' => 'info', default => 'secondary' };
                    @endphp
                    <span class="badge bg-{{ $prioColor }}">{{ ucfirst($maintenance->priority ?? 'N/A') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="col-md-6 mb-3">
        <div class="bg-white rounded shadow-sm p-3 h-100">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3">Información del Mantenimiento</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted fw-bold" style="width: 40%;">Categoría</td>
                    <td>{{ $maintenance->category_label }}</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Tipo</td>
                    <td>{{ ucfirst($maintenance->type ?? 'N/A') }}</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Solicitado por</td>
                    <td>{{ $maintenance->requestedBy?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Fecha Solicitud</td>
                    <td>{{ $maintenance->requested_at?->format('d/m/Y H:i') ?? $maintenance->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td class="text-muted fw-bold">Advisual ID</td>
                    <td>{{ $maintenance->advisual_requisition_id ?? '-' }}</td>
                </tr>
                @if($maintenance->advisual_synced_at)
                <tr>
                    <td class="text-muted fw-bold">Sincronizado</td>
                    <td>{{ $maintenance->advisual_synced_at->format('d/m/Y H:i') }}</td>
                </tr>
                @endif
                @if($maintenance->advisual_sync_error)
                <tr>
                    <td class="text-muted fw-bold">Error Advisual</td>
                    <td class="text-danger">{{ $maintenance->advisual_sync_error }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="bg-white rounded shadow-sm p-3 h-100">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3">Descripción y Cierre</h6>
            <div class="mb-3">
                <small class="text-muted fw-bold d-block mb-1">Descripción:</small>
                <p class="mb-0">{{ $maintenance->description ?? 'Sin descripción' }}</p>
            </div>

            @if($maintenance->status === 'closed')
                <hr>
                <div class="mb-2">
                    <small class="text-muted fw-bold d-block mb-1">Cerrado por:</small>
                    <p class="mb-0">{{ $maintenance->closedBy?->name ?? 'N/A' }}</p>
                </div>
                <div class="mb-2">
                    <small class="text-muted fw-bold d-block mb-1">Fecha de Cierre:</small>
                    <p class="mb-0">{{ $maintenance->closed_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
                @if($maintenance->closure_comment)
                <div class="mb-2">
                    <small class="text-muted fw-bold d-block mb-1">Comentario de Cierre:</small>
                    <p class="mb-0">{{ $maintenance->closure_comment }}</p>
                </div>
                @endif
                @if($maintenance->closure_document_path)
                <div class="mb-2">
                    <small class="text-muted fw-bold d-block mb-1">Documento:</small>
                    <a href="{{ $maintenance->closure_document_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="icon-doc me-1"></i> Ver Documento
                    </a>
                </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Orden de Compra (Advisual) -->
    @if($maintenance->advisual_requisition_id)
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3 d-flex justify-content-between align-items-center">
                <span><i class="icon-doc me-2"></i>Orden de Compra (Advisual)</span>
                @if($maintenance->advisual_purchase_order_id)
                    <span class="badge bg-success">OC #{{ $maintenance->advisual_purchase_order_id }}</span>
                @elseif($maintenance->advisual_purchase_order_sync_error)
                    <span class="badge bg-danger">Error</span>
                @else
                    <span class="badge bg-warning text-dark">Requisición sin OC todavía</span>
                @endif
            </h6>

            @if($maintenance->advisual_purchase_order_sync_error)
                <div class="alert alert-danger py-2 mb-3 small">
                    <i class="icon-exclamation me-1"></i>{{ $maintenance->advisual_purchase_order_sync_error }}
                </div>
            @endif

            @if($maintenance->advisual_purchase_order_id)
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-bold" style="width: 45%;">OC / Línea</td>
                            <td>{{ $maintenance->advisual_purchase_order_id }} / {{ $maintenance->advisual_purchase_order_line_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Descripción</td>
                            <td>{{ $maintenance->advisual_purchase_order_description ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Cantidad</td>
                            <td>{{ $maintenance->advisual_purchase_order_quantity ? rtrim(rtrim(number_format((float) $maintenance->advisual_purchase_order_quantity, 4, '.', ','), '0'), '.') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Valor unitario</td>
                            <td>{{ $maintenance->advisual_purchase_order_unit_price ? '$ ' . number_format((float) $maintenance->advisual_purchase_order_unit_price, 2, ',', '.') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Total</td>
                            <td><strong class="text-success">{{ $maintenance->advisual_purchase_order_total ? '$ ' . number_format((float) $maintenance->advisual_purchase_order_total, 2, ',', '.') : '-' }}</strong></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-bold" style="width: 45%;">Creación OC</td>
                            <td>{{ $maintenance->advisual_purchase_order_created_at?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Fecha compromiso</td>
                            <td>{{ $maintenance->advisual_purchase_order_committed_at?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Fecha ejecución</td>
                            <td>{{ $maintenance->advisual_purchase_order_executed_at?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Última verificación</td>
                            <td>{{ $maintenance->advisual_purchase_order_last_checked_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @else
            <p class="text-muted mb-0 small">
                La requisición {{ $maintenance->advisual_requisition_id }} aún no ha sido convertida en orden de compra en Advisual.
                @if($maintenance->advisual_purchase_order_last_checked_at)
                    Última verificación: {{ $maintenance->advisual_purchase_order_last_checked_at->format('d/m/Y H:i') }}.
                @endif
            </p>
            @endif
        </div>
    </div>
    @endif

    <!-- Close Form (if open) -->
    @if($maintenance->canBeClosed() && auth()->user()->hasAccess('maintenance.close'))
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3">
                <i class="icon-check me-2"></i>Cerrar Mantenimiento
            </h6>
            <form method="POST" action="{{ route('platform.maintenances.close', $maintenance) }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Documento de Cierre (PDF o Imagen) *</label>
                        <input type="file" name="closure_document" class="form-control" accept=".pdf,image/*" required>
                        <small class="text-muted">Formato: PDF, JPG, PNG. Máximo 10MB.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Comentario de Cierre</label>
                        <textarea name="closure_comment" class="form-control" rows="2" placeholder="Nota opcional..."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="icon-check me-1"></i> Cerrar Mantenimiento
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Activity Log -->
    @if(isset($activityLogs) && $activityLogs->count() > 0)
    <div class="col-12 mb-3">
        <div class="bg-white rounded shadow-sm p-3">
            <h6 class="text-dark fw-bold border-bottom pb-2 mb-3">
                <i class="icon-clock me-2"></i>Historial de Actividad
            </h6>
            @foreach($activityLogs as $log)
                <div class="d-flex mb-3 pb-3 border-bottom">
                    <div class="me-3">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="icon-wrench text-white" style="font-size: 14px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-bold text-dark">{{ ucfirst(str_replace('_', ' ', $log->activity_type)) }}</span>
                        <p class="text-muted mb-1 small">{{ $log->description }}</p>
                        <small class="text-muted">
                            <i class="icon-user me-1"></i>{{ $log->user?->name ?? 'Sistema' }}
                            <i class="icon-clock ms-2 me-1"></i>{{ $log->created_at->format('d/m/Y H:i') }}
                        </small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
