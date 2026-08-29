<div class="row">
    <!-- Compact Header -->
    <!-- Compact Header -->
    <div class="col-12 mb-2">
        <div class="bg-white rounded shadow-sm p-3 d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center me-4 mb-2">
                <div class="bg-light rounded p-2 me-3 text-center" style="min-width: 60px;">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">CÓDIGO</small>
                    <span class="fs-5 fw-bold text-dark">{{ $audit->space->external_code }}</span>
                </div>
                <div>
                    <h5 class="mb-0 text-dark">{{ $audit->space->category ?? 'Sin Categoría' }}</h5>
                    <small class="mb-0 text-muted d-block">{{ $audit->space->city }} - {{ $audit->space->location_name }}</small>
                    <small class="mb-0 text-muted d-block">{{ $audit->space->address }} - {{ $audit->space->zone }}</small>
                </div>
            </div>

            <div class="d-flex align-items-center mb-2">
                <div class="me-4 text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">TIPO</small>
                    @if($audit->audit_type === 'structural')
                        <span class="badge bg-purple text-white" style="background-color: #7c3aed;">Estructural</span>
                    @else
                        <span class="badge bg-primary">General</span>
                    @endif
                </div>
                @if($audit->audit_purpose && $audit->audit_purpose !== 'audit_only')
                <div class="me-4 text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">PROPÓSITO</small>
                    @if($audit->audit_purpose === 'preventive_maintenance')
                        <span class="badge text-white" style="background-color: #16a34a;">Mant. Preventivo</span>
                    @elseif($audit->audit_purpose === 'corrective_maintenance')
                        <span class="badge text-white" style="background-color: #ea580c;">Mant. Correctivo</span>
                    @endif
                </div>
                @endif
                <div class="me-4 text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">SEMANA</small>
                    <span class="text-dark fw-bold">{{ $audit->week }} / {{ $audit->year }}</span>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">CLIENTE</small>
                    <span class="fw-bold text-primary">{{ $booking->client_name ?? 'SIN CLIENTE' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-12">
        <div class="bg-white rounded shadow-sm p-3 h-100">
            <!-- Details Table -->
            <h5 class="text-black mb-3 border-bottom pb-2">Detalle de Elementos</h5>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered table-hover align-middle">
                    <thead class="bg-light">
                        <tr class="text-center text-secondary small text-uppercase">
                            <th scope="col" class="text-start">CÓDIGO</th>
                            <th scope="col" style="width: 100px;">BUENO</th>
                            <th scope="col" style="width: 100px;">MALO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audit->values as $val)
                            <tr @if($val->value === 'bad' && !empty($val->comment)) class="align-top" @endif>
                                <td class="fw-bold text-dark">
                                    {{ $val->criterion->name }}
                                    @if($val->value === 'bad' && !empty($val->comment))
                                        <div class="mt-1 small fw-normal text-danger d-flex align-items-start gap-1" style="border-left: 3px solid #dc3545; padding-left: 8px;">
                                            <span>{!! nl2br(e($val->comment)) !!}</span>
                                        </div>
                                    @endif
                                </td>
                                <!-- Option 1: Bueno -->
                                <td class="text-center">
                                        <input type="radio" disabled {{ $val->value === 'good' ? 'checked' : '' }}
                                        class="form-check-input @if($val->value === 'good') border-success bg-success @else border-gray-300 @endif"
                                            style="opacity: 1;">
                                </td>
                                <!-- Option 2: Malo -->
                                <td class="text-center">
                                        <input type="radio" disabled {{ $val->value === 'bad' ? 'checked' : '' }}
                                        class="form-check-input @if($val->value === 'bad') border-danger bg-danger @else border-gray-300 @endif"
                                            style="opacity: 1;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mb-3">
                @if(auth()->user()->hasAccess('audit.upload_fixes') && !$audit->hasOpenMaintenance())
                    <!-- Cargar Revisión (Green) - Triggers Modal -->
                    <button type="button" class="btn btn-success text-white fw-bold px-4" data-bs-toggle="modal"
                        data-bs-target="#uploadRevisionModal">
                        Cargar Revisión
                    </button>
                @endif

                @if(auth()->user()->hasAccess('audit.close_with_error') && !$audit->hasOpenMaintenance())
                    <!-- Editar (Green) -->
                    <button type="button" class="btn btn-success text-white fw-bold px-4" data-bs-toggle="modal"
                        data-bs-target="#editAuditModal">
                        Editar
                    </button>
                @endif

                @if(auth()->user()->hasAccess('audit.request_maintenance') && $audit->general_status === 'bad' && $audit->canRequestMaintenance())
                    <!-- Solicitar Mantenimiento (Warning) -->
                    <button type="button" class="btn btn-warning text-dark fw-bold px-4" data-bs-toggle="modal"
                        data-bs-target="#requestMaintenanceModal">
                        <i class="icon-wrench me-1"></i> Solicitar Mantenimiento
                    </button>
                @endif

                @if(auth()->user()->hasAccess('maintenance.close') && $audit->hasOpenMaintenance())
                    <!-- Cerrar Mantenimiento -->
                    <button type="button" class="btn btn-info text-white fw-bold px-4" data-bs-toggle="modal"
                        data-bs-target="#closeMaintenanceModal">
                        <i class="icon-check me-1"></i> Cerrar Mantenimiento
                    </button>
                @endif
            </div>

            <!-- Maintenance Banner -->
            @php
                $openMaintenances = $audit->maintenances()->with('auditValues.criterion')->whereNotIn('status', ['closed'])->orderBy('requested_at')->get();
                $openMaintenance = $openMaintenances->first();
                $closedMaintenances = $audit->maintenances()->where('status', 'closed')->latest()->get();
                $displayMaintenances = $openMaintenances->isNotEmpty() ? $openMaintenances : $closedMaintenances->take(1);
            @endphp
            @if($displayMaintenances->isNotEmpty())
                @if($openMaintenances->count() + $closedMaintenances->count() > 1)
                    <div class="small text-muted fw-bold mb-2">
                        <i class="icon-wrench me-1"></i>{{ $openMaintenances->count() }} abiertas · {{ $closedMaintenances->count() }} cerradas
                    </div>
                @endif
                @foreach($displayMaintenances as $dm)
                    <div class="alert {{ $dm->status === 'closed' ? 'alert-success' : 'alert-info' }} d-flex align-items-center mb-2 py-2">
                        <i class="icon-wrench me-3" style="font-size: 1.3rem;"></i>
                        <div class="flex-grow-1">
                            <strong>#{{ $dm->id }} <span class="badge bg-{{ $dm->status_color }}">{{ $dm->status_label }}</span>
                                @if($dm->category)
                                    <span class="badge bg-light text-dark border ms-1">{{ ucfirst($dm->category) }}</span>
                                @endif
                            </strong>
                            <div class="small text-muted mt-1">
                                <span class="me-3"><i class="icon-calendar me-1"></i>{{ $dm->requested_at?->format('d/m/Y H:i') ?? $dm->created_at->format('d/m/Y H:i') }}</span>
                                <span class="me-3"><i class="icon-user me-1"></i>{{ $dm->requestedBy?->name ?? 'N/A' }}</span>
                                @if($dm->advisual_requisition_id)
                                    <span class="me-3"><i class="icon-tag me-1"></i>RQ {{ $dm->advisual_requisition_id }}</span>
                                @endif
                                @if($dm->advisual_purchase_order_id)
                                    <span class="me-3 text-success"><i class="icon-doc me-1"></i>OC {{ $dm->advisual_purchase_order_id }}@if($dm->advisual_purchase_order_total) — $ {{ number_format((float) $dm->advisual_purchase_order_total, 0, ',', '.') }}@endif</span>
                                @endif
                                @if($dm->closed_at)
                                    <span><i class="icon-check me-1"></i>{{ $dm->closed_at->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                            @if($dm->advisual_sync_error)
                                <div class="small text-danger mt-1"><i class="icon-exclamation me-1"></i>{{ $dm->advisual_sync_error }}</div>
                            @endif
                        </div>
                        <a href="{{ route('platform.maintenances.detail', $dm) }}" class="btn btn-sm btn-outline-dark ms-2">
                            <i class="icon-eye"></i> Detalle
                        </a>
                    </div>
                @endforeach
            @endif
            
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                // Función para evitar la alerta de cambios sin guardar de Orchid
                function bypassOrchidDirtyCheck() {
                    const postForm = document.getElementById('post-form');
                    if (postForm) {
                        postForm.dataset.formHasBeenChangedValue = 'false';
                        postForm.dataset.formNeedPreventsFormAbandonmentValue = 'false';
                    }
                    window.onbeforeunload = null;
                }
            </script>

            <!-- Observation (sin título) -->
            @if($audit->observation)
                <div class="mb-3 p-3 bg-light border rounded">
                    <div class="d-flex align-items-start">
                        @if($audit->user)
                            <img src="{{ $audit->user->presenter()->image() }}" 
                                class="rounded-circle me-3 flex-shrink-0" 
                                style="width: 36px; height: 36px; object-fit: cover;"
                                alt="{{ $audit->user->name }}">
                        @else
                            <div class="bg-secondary bg-opacity-25 rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width: 36px; height: 36px;">
                                <i class="icon-bubble text-secondary"></i>
                            </div>
                        @endif
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <span class="fw-bold text-dark me-2">{{ $audit->user->name ?? 'Auditor' }}</span>
                                <small class="text-muted">
                                    {{ $audit->created_at->format('d/m/Y H:i') }}
                                </small>
                            </div>
                            <p class="mb-0 text-muted">{{ $audit->observation }}</p>
                        </div>
                    </div>
                </div>
            @endif


            <!-- Gallery -->
            <div class="border-top pt-3">
                @php($evidencePdf = $audit->photos->firstWhere('file_type', 'pdf'))
                @if($evidencePdf)
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-black mb-0">Evidencia PDF</h5>
                        <a href="{{ $evidencePdf->url }}" target="_blank" class="btn btn-sm btn-outline-primary">Descargar / abrir</a>
                    </div>
                    <iframe src="{{ $evidencePdf->url }}" class="w-100 border rounded" style="height: 70vh;"></iframe>
                @else
                <h5 class="text-black mb-3">Imágenes</h5>
                @if($audit->photos->count() > 0)
                    <div class="row g-2">
                        @foreach($audit->photos as $key => $photo)
                            <div class="col-6 col-md-3 col-lg-2">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#galleryModal"
                                   data-slide-to="{{ $key }}"
                                   class="d-block ratio ratio-4x3 border rounded overflow-hidden position-relative">
                                    <img src="{{ $photo->url }}" alt="Foto"
                                        class="w-100 h-100 object-fit-cover" style="object-fit: cover;">
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">No hay imágenes registradas.</p>
                @endif
                @endif
            </div>

        </div>
    </div>

    <!-- History Section -->
    <div class="col-12 mt-4">
        <div class="bg-white rounded shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h5 class="text-black mb-0">
                    <i class="icon-clock me-2"></i>Historial del Espacio
                </h5>
                <span class="badge bg-secondary">{{ $audit->space->external_code }}</span>
            </div>


            <div>
                @php
                    $catColors = [
                        'audit_created' => ['bg' => '#dcfce7', 'fg' => '#166534'],
                        'audit_updated' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
                        'resolution_uploaded' => ['bg' => '#e0e7ff', 'fg' => '#3730a3'],
                        'status_changed' => ['bg' => '#f1f5f9', 'fg' => '#334155'],
                        'maintenance_requested' => ['bg' => '#fef3c7', 'fg' => '#92400e'],
                        'maintenance_closed' => ['bg' => '#dcfce7', 'fg' => '#166534'],
                    ];
                    $catLabels = [
                        'audit_created' => 'Auditoría',
                        'audit_updated' => 'Auditoría',
                        'resolution_uploaded' => 'Revisión',
                        'status_changed' => 'Estado',
                        'maintenance_requested' => 'Mantenimiento',
                        'maintenance_closed' => 'Mantenimiento',
                    ];
                @endphp

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Vista cronológica · arrastra horizontal para navegar</small>
                    <div class="fishbone-toggle" id="historyViewToggle">
                        <button type="button" class="active" data-view="fishbone">Espina</button>
                        <button type="button" data-view="list">Lista</button>
                    </div>
                </div>

                @if(isset($activityLogs) && $activityLogs->count() > 0)
                    {{-- ESPINA DE PESCADO --}}
                    <div class="fishbone" id="historyFishbone">
                        <div class="fishbone__track">
                            <div class="fishbone__spine"></div>

                            <div class="fishbone__head">
                                <i class="icon-flag"></i> Estado actual
                            </div>

                            @php $orderedLogs = $activityLogs->values(); @endphp
                            @foreach($orderedLogs as $i => $log)
                                @php
                                    $position = $i % 2 === 0 ? 'left' : 'right';
                                    $cat = $catColors[$log->activity_type] ?? ['bg' => '#f1f5f9', 'fg' => '#334155'];
                                    $catLabel = $catLabels[$log->activity_type] ?? 'Evento';
                                    $isCurrent = $log->audit_id && $log->audit_id === $audit->id;
                                    $titleText = match($log->activity_type) {
                                        'audit_created' => 'Auditoría Creada',
                                        'audit_updated' => 'Auditoría Actualizada',
                                        'resolution_uploaded' => 'Revisión Cargada',
                                        'status_changed' => 'Estado Cambiado',
                                        'maintenance_requested' => 'Mantenimiento Solicitado',
                                        'maintenance_closed' => 'Mantenimiento Cerrado',
                                        default => ucfirst(str_replace('_', ' ', $log->activity_type)),
                                    };
                                    $cleanDescription = preg_replace('/\s*con estado:\s*(good|bad)\s*\.?/i', '', $log->description);
                                    $cleanDescription = preg_replace('/\s*Estado:\s*(good|bad)\s*\.?/i', '', $cleanDescription);
                                    $statusValue = $log->metadata['general_status'] ?? null;
                                @endphp
                                <div class="fishbone__event fishbone__event--{{ $position }}">
                                    <div class="fishbone__bone"></div>
                                    <div class="fishbone__dot" style="border-color: {{ $cat['fg'] }};"></div>
                                    <div class="fishbone__card {{ $isCurrent ? 'is-current' : '' }}">
                                        <span class="fishbone__cat" style="background: {{ $cat['bg'] }}; color: {{ $cat['fg'] }};">{{ $catLabel }}</span>
                                        @if($log->week && $log->year)
                                            <span class="fishbone__cat" style="background: #f1f5f9; color: #475569;">S{{ $log->week }}/{{ $log->year }}</span>
                                        @endif
                                        <div class="fishbone__title">{{ $titleText }}</div>
                                        <div class="fishbone__meta" title="{{ $log->description }}">
                                            {{ \Illuminate\Support\Str::limit(trim($cleanDescription), 80) }}
                                            @if($statusValue === 'good')
                                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;">Bueno</span>
                                            @elseif($statusValue === 'bad')
                                                <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">Malo</span>
                                            @endif
                                        </div>
                                        <div class="fishbone__foot">
                                            <span title="{{ $log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema') }}">
                                                <i class="icon-user"></i> {{ \Illuminate\Support\Str::limit($log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema'), 12) }}
                                            </span>
                                            <span>{{ $log->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        @if($log->audit_id)
                                            <div class="mt-2">
                                                @if($isCurrent)
                                                    <span class="badge bg-light text-dark border w-100" style="font-size: 0.65rem;">
                                                        <i class="icon-eye me-1"></i> Vista actual
                                                    </span>
                                                @else
                                                    <a href="{{ route('platform.audit.detail', $log->audit_id) }}"
                                                       class="btn btn-sm btn-outline-primary py-0 px-2 w-100" style="font-size: 0.7rem;">
                                                        <i class="icon-eye me-1"></i> Ver auditoría
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- LISTA (oculta por defecto) --}}
                    <div class="timeline-container" id="historyList" style="display: none; max-height: 400px; overflow-y: auto;">
                        @foreach($activityLogs as $log)
                            <div class="d-flex mb-3 pb-3 border-bottom" style="overflow: hidden;">
                                    <div class="flex-shrink-0 me-3">
                                        @php
                                            $iconClass = match($log->activity_type) {
                                                'audit_created' => 'bg-success',
                                                'audit_updated' => 'bg-info',
                                                'marked_third_party' => 'bg-warning',
                                                'resolution_uploaded' => 'bg-primary',
                                                'status_changed' => 'bg-secondary',
                                                'maintenance_requested' => 'bg-warning',
                                                'maintenance_closed' => 'bg-success',
                                                default => 'bg-secondary',
                                            };
                                            $icon = match($log->activity_type) {
                                                'audit_created' => 'icon-plus',
                                                'audit_updated' => 'icon-pencil',
                                                'marked_third_party' => 'icon-people',
                                                'resolution_uploaded' => 'icon-camera',
                                                'status_changed' => 'icon-refresh',
                                                'maintenance_requested' => 'icon-wrench',
                                                'maintenance_closed' => 'icon-check',
                                                default => 'icon-circle',
                                            };
                                        @endphp
                                        @if($log->user)
                                            <div class="position-relative">
                                                <img src="{{ $log->user->presenter()->image() }}" 
                                                    class="rounded-circle" 
                                                    style="width: 36px; height: 36px; object-fit: cover;"
                                                    alt="{{ $log->user->name }}">
                                                <div class="position-absolute bottom-0 end-0 rounded-circle {{ $iconClass }} d-flex align-items-center justify-content-center border border-white" 
                                                     style="width: 16px; height: 16px; margin-bottom: -2px; margin-right: -2px;">
                                                    <i class="{{ $icon }} text-white" style="font-size: 8px;"></i>
                                                </div>
                                            </div>
                                        @else
                                            <div class="rounded-circle {{ $iconClass }} d-flex align-items-center justify-content-center" 
                                                 style="width: 36px; height: 36px;">
                                                <i class="{{ $icon }} text-white" style="font-size: 14px;"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div style="min-width: 0;">
                                                <span class="fw-bold text-dark">
                                                    @switch($log->activity_type)
                                                        @case('audit_created')
                                                            Auditoría Creada
                                                            @break
                                                        @case('audit_updated')
                                                            Auditoría Actualizada
                                                            @break
                                                        @case('marked_third_party')
                                                            Marcado como Tercero
                                                            @break
                                                        @case('resolution_uploaded')
                                                            Revisión Cargada
                                                            @break
                                                        @case('status_changed')
                                                            Estado Cambiado
                                                            @break
                                                        @case('maintenance_requested')
                                                            Mantenimiento Solicitado
                                                            @break
                                                        @case('maintenance_closed')
                                                            Mantenimiento Cerrado
                                                            @break
                                                        @default
                                                            {{ ucfirst(str_replace('_', ' ', $log->activity_type)) }}
                                                    @endswitch
                                                </span>
                                                @if($log->week && $log->year)
                                                    <span class="badge bg-light text-dark ms-2">S{{ $log->week }}/{{ $log->year }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        @php
                                            $cleanDescription = preg_replace('/\s*con estado:\s*(good|bad)\s*\.?/i', '', $log->description);
                                            $cleanDescription = preg_replace('/\s*Estado:\s*(good|bad)\s*\.?/i', '', $cleanDescription);
                                            $statusValue = $log->metadata['general_status'] ?? null;
                                        @endphp
                                        <p class="text-muted mb-1 small d-flex align-items-center flex-wrap gap-2" title="{{ $log->description }}">
                                            <span class="text-truncate">{{ trim($cleanDescription) }}</span>
                                            @if($statusValue === 'good')
                                                <span class="badge bg-success">Bueno</span>
                                            @elseif($statusValue === 'bad')
                                                <span class="badge bg-danger">Malo</span>
                                            @endif
                                        </p>
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <small class="text-muted">
                                                <i class="icon-user me-1"></i>
                                                {{ $log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema') }}
                                            </small>
                                            <small class="text-muted">
                                                <i class="icon-clock me-1"></i>
                                                {{ $log->created_at->format('d/m/Y H:i') }}
                                            </small>

                                            @if($log->audit_id)
                                                @if($log->audit_id !== $audit->id)
                                                    <a href="{{ route('platform.audit.detail', $log->audit_id) }}"
                                                        class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                        <i class="icon-eye me-1"></i> Ver auditoría
                                                    </a>
                                                @else
                                                    <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                        <i class="icon-eye me-1"></i> Vista actual
                                                    </span>
                                                @endif
                                            @endif

                                            {{-- Botón para ver foto si existe --}}
                                            @if(isset($log->metadata['photo_path']) && $log->metadata['photo_path'])
                                                <button type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#singlePhotoModal"
                                                    data-photo-url="{{ \Storage::disk('s3')->url($log->metadata['photo_path']) }}"
                                                    class="btn btn-sm btn-outline-success py-0 px-2 btn-view-photo" style="font-size: 0.75rem;">
                                                    <i class="icon-camera me-1"></i> Ver Foto
                                                </button>
                                            @endif
                                        </div>
                                        
                                        @if($log->metadata)
                                            {{-- Cambio de estado --}}
                                            @if(isset($log->metadata['old_status']) && isset($log->metadata['new_status']))
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        Estado:
                                                        <span class="badge {{ $log->metadata['old_status'] === 'bad' ? 'bg-danger' : 'bg-success' }}">
                                                            {{ ucfirst($log->metadata['old_status']) }}
                                                        </span>
                                                        →
                                                        <span class="badge {{ $log->metadata['new_status'] === 'bad' ? 'bg-danger' : 'bg-success' }}">
                                                            {{ ucfirst($log->metadata['new_status']) }}
                                                        </span>
                                                    </small>
                                                </div>
                                            @endif

                                            {{-- Comentario/Observación --}}
                                            @if(isset($log->metadata['comment']) && $log->metadata['comment'])
                                                <div class="mt-2 p-2 bg-light rounded border-start border-3 border-secondary" style="overflow: hidden; word-break: break-word;">
                                                    <small class="text-muted d-block mb-1"><i class="icon-bubble me-1"></i> Observación:</small>
                                                    <p class="mb-0 small text-dark">{{ $log->metadata['comment'] }}</p>
                                                </div>
                                            @endif
                                            @if(isset($log->metadata['added_comment']) && $log->metadata['added_comment'])
                                                <div class="mt-2 p-2 bg-light rounded border-start border-3 border-info" style="overflow: hidden; word-break: break-word;">
                                                    <small class="text-muted d-block mb-1"><i class="icon-pencil me-1"></i> Nota:</small>
                                                    <p class="mb-0 small text-dark">{{ $log->metadata['added_comment'] }}</p>
                                                </div>
                                            @endif

                                            {{-- Cambios de criterios --}}
                                            @if(isset($log->metadata['criteria_changes']) && count($log->metadata['criteria_changes']) > 0)
                                                <div class="mt-2" style="overflow: hidden;">
                                                    <small class="text-muted d-block mb-1"><i class="icon-list me-1"></i> Criterios modificados:</small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($log->metadata['criteria_changes'] as $change)
                                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                                {{ $change['criterion'] }}:
                                                                <span class="{{ $change['old'] === 'bad' ? 'text-danger' : 'text-success' }}">{{ ucfirst($change['old']) }}</span>
                                                                →
                                                                <span class="{{ $change['new'] === 'bad' ? 'text-danger' : 'text-success' }}">{{ ucfirst($change['new']) }}</span>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="icon-clock" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No hay actividad registrada para este espacio.</p>
                        <small>Las actividades futuras aparecerán aquí.</small>
                    </div>
                @endif
            </div>

            @push('stylesheets')
                <link rel="stylesheet" href="{{ asset('css/fishbone.css') }}">
            @endpush

            @push('scripts')
                <script src="{{ asset('js/fishbone.js') }}"></script>
            @endpush

            </div>

        </div>
    </div>
</div>

<!-- Modal Cargar Revisión -->
<div class="modal fade" id="uploadRevisionModal" tabindex="-1" aria-labelledby="uploadRevisionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="revision-container"> <!-- Reemplaza form tag -->
            <div class="modal-content border-0 shadow" style="border-radius: 6px;">
                <div class="modal-header border-bottom py-3 px-4 bg-white">
                    <h5 class="modal-title text-dark fw-light" id="uploadRevisionModalLabel">
                        Cargar Revisión
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4 bg-light bg-opacity-10">
                    <!-- Criterios con radio buttons -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Estado de Criterios</span>
                            <button type="button" class="btn btn-link btn-sm text-success text-decoration-none p-0 fw-bold" onclick="markAllGoodInModal('uploadRevisionModal')" style="font-size: 0.8rem;">
                                Marcar todo OK
                            </button>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0 align-middle">
                                    <thead class="border-bottom bg-light">
                                        <tr class="text-center text-muted text-uppercase" style="font-size: 0.7rem;">
                                            <th class="text-start py-2 ps-3 fw-normal">Criterio</th>
                                            <th class="fw-normal" style="width: 60px;">Bueno</th>
                                            <th class="fw-normal" style="width: 60px;">Malo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach($audit->values as $val)
                                            <tr class="border-bottom-light @if($val->value === 'bad' && !empty($val->comment)) align-top @endif">
                                                <td class="ps-3 py-2 text-dark small">
                                                    {{ $val->criterion->name }}
                                                    @if($val->value === 'bad' && !empty($val->comment))
                                                        <div class="mt-1 text-danger" style="font-size: 0.75rem; border-left: 3px solid #dc3545; padding-left: 6px;">
                                                            {!! nl2br(e($val->comment)) !!}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="good"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'good' ? 'bg-success border-success' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'good' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'success')">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="bad"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'bad' ? 'bg-danger border-danger' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'bad' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'danger')">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Foto con compresión -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Foto de Revisión *</label>
                            <input type="file" class="form-control form-control-sm bg-white" id="revision_photo_input" accept="image/*" required>
                            <div class="invalid-feedback">Por favor selecciona una foto.</div>
                            <div id="photo_preview" class="mt-2 d-none">
                                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                    <img id="photo_preview_img" src="" alt="Preview" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <small class="text-success fw-bold d-block">Imagen lista</small>
                                        <small class="text-muted" id="photo_size_info"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearRevisionPhoto()">
                                        <i class="icon-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Observación *</label>
                            <textarea class="form-control form-control-sm bg-white" name="revision_comment" id="revision_comment_input" rows="2"
                                placeholder="Descripción breve de la reparación..." required></textarea>
                            <div class="invalid-feedback">Por favor ingresa una observación.</div>
                        </div>
                        
                        <!-- Emails -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Notificar a (Opcional)</label>
                            <input type="text" class="form-control form-control-sm bg-white" name="client_emails" 
                                placeholder="correos separados por coma">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-white py-2 px-4">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" onclick="submitRevisionForm()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Auditoría -->
<div class="modal fade" id="editAuditModal" tabindex="-1" aria-labelledby="editAuditModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="revision-container"> <!-- Reemplaza form tag -->
            <div class="modal-content border-0 shadow" style="border-radius: 6px;">
                <div class="modal-header border-bottom py-3 px-4 bg-white">
                    <h5 class="modal-title text-dark fw-light" id="uploadRevisionModalLabel">
                        Editar Auditoría
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4 bg-light bg-opacity-10">
                    <!-- Criterios con radio buttons -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Estado de Criterios</span>
                            <button type="button" class="btn btn-link btn-sm text-success text-decoration-none p-0 fw-bold" onclick="markAllGoodInModal('editAuditModal')" style="font-size: 0.8rem;">
                                Marcar todo OK
                            </button>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0 align-middle">
                                    <thead class="border-bottom bg-light">
                                        <tr class="text-center text-muted text-uppercase" style="font-size: 0.7rem;">
                                            <th class="text-start py-2 ps-3 fw-normal">Criterio</th>
                                            <th class="fw-normal" style="width: 60px;">Bueno</th>
                                            <th class="fw-normal" style="width: 60px;">Malo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach($audit->values as $val)
                                            <tr class="border-bottom-light @if($val->value === 'bad' && !empty($val->comment)) align-top @endif">
                                                <td class="ps-3 py-2 text-dark small">
                                                    {{ $val->criterion->name }}
                                                    @if($val->value === 'bad' && !empty($val->comment))
                                                        <div class="mt-1 text-danger" style="font-size: 0.75rem; border-left: 3px solid #dc3545; padding-left: 6px;">
                                                            {!! nl2br(e($val->comment)) !!}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="good"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'good' ? 'bg-success border-success' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'good' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'success')">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="bad"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'bad' ? 'bg-danger border-danger' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'bad' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'danger')">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        
                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Nota de Edición *</label>
                            <textarea class="form-control form-control-sm bg-white" name="revision_comment" id="edit_comment_input" rows="2"
                                placeholder="Explica brevemente el motivo del cambio..." required></textarea>
                            <div class="invalid-feedback">Por favor ingresa una nota de edición (mínimo 3 caracteres).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-white py-2 px-4">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" onclick="submitEditAuditForm()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compressor.js para compresión de imagen -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/compressorjs/1.2.1/compressor.min.js"></script>

<script>
    // Variable para almacenar la imagen comprimida
    let compressedRevisionPhoto = null;
    
    // Estado original de los criterios (para detectar cambios)
    const originalCriteria = {
        @foreach($audit->values as $val)
        '{{ $val->id }}': '{{ $val->value }}',
        @endforeach
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Mover los modales al final del body para evitar conflictos de formularios anidados
        ['uploadRevisionModal', 'editAuditModal', 'singlePhotoModal', 'galleryModal', 'requestMaintenanceModal', 'closeMaintenanceModal'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        // Evento para comprimir imagen al seleccionar
        const fileInput = document.getElementById('revision_photo_input');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                fileInput.classList.remove('is-invalid'); // Limpiar error al seleccionar
                const file = e.target.files[0];
                if (file) {
                    compressImage(file);
                }
            });
        }
        
        // Limpiar errores al escribir en los campos de texto
        const revisionComment = document.getElementById('revision_comment_input');
        if (revisionComment) {
            revisionComment.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
        
        const editComment = document.getElementById('edit_comment_input');
        if (editComment) {
            editComment.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
    });
    
    // Función para detectar si hay cambios en los criterios de un modal
    function hasChangesInCriteria(modalId) {
        const modal = document.getElementById(modalId);
        let hasChanges = false;
        
        modal.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            // Extraer el ID del criterio del nombre (criteria[123] -> 123)
            const match = radio.name.match(/criteria\[(\d+)\]/);
            if (match) {
                const criteriaId = match[1];
                const currentValue = radio.value;
                const originalValue = originalCriteria[criteriaId];
                
                if (currentValue !== originalValue) {
                    hasChanges = true;
                }
            }
        });
        
        return hasChanges;
    }

    function compressImage(file) {
        const preview = document.getElementById('photo_preview');
        const previewImg = document.getElementById('photo_preview_img');
        const sizeInfo = document.getElementById('photo_size_info');
        
        // Mostrar estado de carga
        preview.classList.remove('d-none');
        sizeInfo.textContent = 'Comprimiendo...';
        previewImg.src = '';

        new Compressor(file, {
            quality: 0.85,
            maxWidth: 2048,
            maxHeight: 2048,
            mimeType: 'image/jpeg',
            convertSize: 500000,
            success(result) {
                compressedRevisionPhoto = new File([result], result.name || 'revision.jpg', { type: result.type });
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                };
                reader.readAsDataURL(compressedRevisionPhoto);
                
                // Mostrar tamaño
                const originalSize = (file.size / 1024).toFixed(0);
                const compressedSize = (compressedRevisionPhoto.size / 1024).toFixed(0);
                sizeInfo.textContent = `${originalSize}KB → ${compressedSize}KB`;
            },
            error(err) {
                console.error('Error comprimiendo:', err);
                // Usar original si falla la compresión
                compressedRevisionPhoto = file;
                sizeInfo.textContent = 'Usando imagen original';
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    function clearRevisionPhoto() {
        compressedRevisionPhoto = null;
        const fileInput = document.getElementById('revision_photo_input');
        if (fileInput) fileInput.value = '';
        document.getElementById('photo_preview').classList.add('d-none');
    }

    function submitRevisionForm() {
        const modal = document.getElementById('uploadRevisionModal');
        const fileInput = document.getElementById('revision_photo_input');
        const commentInput = document.getElementById('revision_comment_input');
        const emailsInput = modal.querySelector('input[name="client_emails"]');
        
        // Verificar si hay cambios en los criterios
        if (!hasChangesInCriteria('uploadRevisionModal')) {
            Swal.fire({
                icon: 'info',
                title: 'Sin cambios',
                text: 'No se detectaron cambios en los criterios. Modifica al menos un criterio para guardar.',
                confirmButtonColor: '#198754'
            });
            return;
        }
        
        // Limpiar errores previos
        fileInput.classList.remove('is-invalid');
        commentInput.classList.remove('is-invalid');
        
        let hasErrors = false;
        
        // Validación de foto
        if (!compressedRevisionPhoto) {
            fileInput.classList.add('is-invalid');
            hasErrors = true;
        }
        
        // Validación de observación (requerida)
        if (!commentInput.value || commentInput.value.trim().length < 3) {
            commentInput.classList.add('is-invalid');
            hasErrors = true;
        }
        
        if (hasErrors) {
            return;
        }

        // Construir FormData
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('revision_photo', compressedRevisionPhoto);
        formData.append('revision_comment', commentInput.value || '');
        formData.append('client_emails', emailsInput.value || '');
        
        // Agregar criterios
        modal.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            formData.append(radio.name, radio.value);
        });

        const actionUrl = '{{ url("/admin/audit-action/" . $audit->id . "/upload-revision") }}';

        // Mostrar loading
        Swal.fire({
            title: 'Guardando...',
            text: 'Subiendo revisión y actualizando estado',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar
        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.redirected) {
                window.onbeforeunload = null;
                window.location.href = response.url;
            } else if (response.ok) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'La revisión se ha cargado correctamente.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Limpiar para evitar advertencia de Chrome
                    bypassOrchidDirtyCheck();
                    clearModalInputs('uploadRevisionModal');
                    window.location.reload();
                });
            } else {
                return response.text().then(text => {
                    try {
                        const json = JSON.parse(text);
                        throw new Error(json.message || 'Error al guardar');
                    } catch(e) {
                        throw new Error('Error en el servidor al guardar la revisión');
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message,
                confirmButtonColor: '#dc3545'
            });
        });
    }

    function markAllGood() {
        document.querySelectorAll('#uploadRevisionModal input[type="radio"][value="good"]').forEach(radio => {
            radio.checked = true;
            updateRadioStyle(radio, 'success');
        });
    }
    
    function updateRadioStyle(radio, color) {
        // Clear all radios in the same row
        const row = radio.closest('tr');
        row.querySelectorAll('input[type="radio"]').forEach(r => {
            r.classList.remove('bg-success', 'border-success', 'bg-warning', 'border-warning', 'bg-danger', 'border-danger');
        });
        // Add style to selected
        radio.classList.add('bg-' + color, 'border-' + color);
    }

    function submitEditAuditForm() {
        const modal = document.getElementById('editAuditModal');
        const commentInput = document.getElementById('edit_comment_input');
        
        // Verificar si hay cambios en los criterios
        if (!hasChangesInCriteria('editAuditModal')) {
            Swal.fire({
                icon: 'info',
                title: 'Sin cambios',
                text: 'No se detectaron cambios en los criterios. Modifica al menos un criterio para guardar.',
                confirmButtonColor: '#198754'
            });
            return;
        }
        
        // Limpiar errores previos
        commentInput.classList.remove('is-invalid');
        
        // Validación estilo Orchid
        if (!commentInput.value || commentInput.value.trim().length < 3) {
            commentInput.classList.add('is-invalid');
            return;
        }
        
        // Construir FormData
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('revision_comment', commentInput.value.trim());
        
        // Agregar criterios
        modal.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            formData.append(radio.name, radio.value);
        });

        const actionUrl = '{{ url("/admin/audit-action/" . $audit->id . "/update") }}';

        // Mostrar loading
        Swal.fire({
            title: 'Actualizando...',
            text: 'Guardando cambios de la auditoría',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // Enviar
        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (response.redirected) {
                window.onbeforeunload = null;
                window.location.href = response.url;
            } else if (response.ok) {
                 Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'Los datos han sido guardados.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Limpiar para evitar advertencia de Chrome
                    bypassOrchidDirtyCheck();
                    clearModalInputs('editAuditModal');
                    
                    if (typeof Turbo !== 'undefined') {
                        Turbo.visit(window.location.href, { action: "replace" });
                    } else {
                        window.location.reload();
                    }
                });
            } else {
                return response.text().then(text => { throw new Error(text) });
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire('Error', 'No se pudo actualizar la auditoría', 'error');
        });
    }

    function markAllGoodInModal(modalId) {
        document.querySelectorAll('#' + modalId + ' input[type="radio"][value="good"]').forEach(radio => {
            radio.checked = true;
            updateRadioStyle(radio, 'success');
        });
    }
    
    // Listener para cargar foto en el modal cuando se hace clic en "Ver Foto"
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-view-photo');
        if (btn) {
            const photoUrl = btn.getAttribute('data-photo-url');
            const img = document.getElementById('singlePhotoImg');
            if (img && photoUrl) {
                img.src = photoUrl;
            }
        }
    });

    // Galería: abrir el carrusel en la foto que se hizo clic (no siempre la primera).
    // Se fija el item activo directamente en el evento show (sin depender de la
    // animación del carrusel, que falla mientras el modal está oculto).
    const galleryModalEl = document.getElementById('galleryModal');
    if (galleryModalEl) {
        galleryModalEl.addEventListener('show.bs.modal', function (e) {
            const idx = parseInt(e.relatedTarget?.getAttribute('data-slide-to') || '0', 10);
            const items = galleryModalEl.querySelectorAll('#carouselGallery .carousel-item');
            items.forEach((item, i) => item.classList.toggle('active', i === idx));
        });
    }
    
    // Limpiar inputs del modal para evitar advertencia de Chrome al recargar
    function clearModalInputs(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Limpiar textareas
        modal.querySelectorAll('textarea').forEach(ta => {
            ta.value = '';
            ta.defaultValue = '';
        });
        
        // Limpiar inputs de texto
        modal.querySelectorAll('input[type="text"], input[type="email"]').forEach(input => {
            input.value = '';
            input.defaultValue = '';
        });
        
        // Limpiar file inputs
        modal.querySelectorAll('input[type="file"]').forEach(input => {
            input.value = '';
        });
        
        // Cerrar el modal
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
    }

    function submitCloseMaintenance() {
        var closureDoc = document.getElementById('closure_document_input');
        var closureComment = document.getElementById('closure_comment_input');

        if (!closureDoc.files || closureDoc.files.length === 0) {
            closureDoc.classList.add('is-invalid');
            return;
        }

        var formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('closure_document', closureDoc.files[0]);
        formData.append('closure_comment', closureComment.value);

        var maintenanceSelect = document.getElementById('close_maintenance_id_input');
        if (maintenanceSelect && maintenanceSelect.value) {
            formData.append('maintenance_id', maintenanceSelect.value);
        }

        var actionUrl = '{{ url("/admin/audit-action/" . $audit->id . "/close-maintenance") }}';

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.ok || response.status === 302) {
                bypassOrchidDirtyCheck();
                window.location.reload();
            } else {
                return response.json().then(data => {
                    var msg = data.message || 'Error al cerrar el mantenimiento.';
                    if (data.errors) {
                        msg = Object.values(data.errors).flat().join('\n');
                    }
                    alert(msg);
                });
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error al cerrar el mantenimiento.');
        });
    }

    function submitRequestMaintenance() {
        var priority = document.getElementById('maintenance_priority_input').value;
        var description = document.getElementById('maintenance_description_input').value;
        var auditValueIds = Array.from(
            document.querySelectorAll('#maintenance_criteria_list input[name="audit_value_ids[]"]:checked')
        ).map(function (cb) { return parseInt(cb.value, 10); });

        if (!auditValueIds.length) {
            alert('Selecciona al menos un criterio a corregir.');
            return;
        }
        if (!description || description.length < 5) {
            document.getElementById('maintenance_description_input').classList.add('is-invalid');
            return;
        }

        var btn = document.querySelector('#requestMaintenanceModal .btn-warning');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

        fetch('{{ url("/admin/audit-action/" . $audit->id . "/request-maintenance") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                audit_value_ids: auditValueIds,
                maintenance_priority: priority,
                maintenance_description: description,
            }),
        })
        .then(function(response) {
            return response.json().then(function(data) {
                if (!response.ok) throw data;
                return data;
            });
        })
        .then(function(data) {
            Swal.fire({
                icon: 'success',
                title: '¡Solicitud Creada!',
                text: 'El mantenimiento ha sido solicitado correctamente.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                bypassOrchidDirtyCheck();
                window.location.reload();
            });
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = 'Solicitar';
            alert(error.message || 'Error al solicitar mantenimiento.');
        });
    }
</script>

<!-- Modal para foto individual (de actividad) -->
<div class="modal fade" id="singlePhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center" style="min-height: 500px;">
                <div class="d-flex justify-content-center align-items-center" style="height: 80vh;">
                    <img id="singlePhotoImg" src="" class="mw-100 mh-100" alt="Foto de revisión" style="object-fit: contain;">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Galería (Carousel Lightbox) -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center" style="min-height: 500px;">
                <div id="carouselGallery" class="carousel slide w-100 h-100" data-bs-interval="false">
                    <div class="carousel-inner h-100">
                        @foreach($audit->photos as $key => $photo)
                            <div class="carousel-item h-100 {{ $key == 0 ? 'active' : '' }}">
                                <div class="d-flex justify-content-center align-items-center" style="height: 80vh;">
                                    <img src="{{ $photo->url }}"
                                         class="mw-100 mh-100" 
                                         alt="Foto {{ $key + 1 }}" 
                                         style="object-fit: contain;">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselGallery" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselGallery" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
 
<!-- Modal Solicitar Mantenimiento -->
<div class="modal fade" id="requestMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 6px;">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <h5 class="modal-title text-dark fw-light">
                    <i class="icon-wrench me-2"></i>Solicitar Mantenimiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Tipo de Mantenimiento</label>
                        <input type="hidden" id="maintenance_type_input" value="corrective">
                        <div class="form-control form-control-sm bg-light text-muted" style="pointer-events: none;">Correctivo</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Criterios a corregir *</label>
                        @php $uncoveredBad = $audit->uncoveredBadValues(); @endphp
                        <div id="maintenance_criteria_list" class="border rounded p-2 bg-light" style="max-height: 220px; overflow-y: auto;">
                            @forelse($uncoveredBad as $val)
                                <div class="form-check">
                                    <input class="form-check-input maintenance-criteria-checkbox" type="checkbox"
                                        name="audit_value_ids[]" value="{{ $val->id }}" id="av_{{ $val->id }}">
                                    <label class="form-check-label d-flex align-items-center justify-content-between" for="av_{{ $val->id }}">
                                        <span>{{ $val->criterion->name }}</span>
                                        <span class="badge bg-secondary text-uppercase ms-2" style="font-size: 0.65rem;">
                                            {{ $val->criterion->applies_to ?? '—' }}
                                        </span>
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">No hay criterios pendientes por cubrir.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Prioridad *</label>
                        <select id="maintenance_priority_input" class="form-select form-select-sm" required>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Descripción del Problema *</label>
                        <textarea id="maintenance_description_input" class="form-control form-control-sm" rows="3"
                            placeholder="Describa el problema encontrado..." required minlength="5"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white py-2 px-4">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning text-dark px-4 fw-bold" onclick="submitRequestMaintenance()">Solicitar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cerrar Mantenimiento -->
<div class="modal fade" id="closeMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 6px;">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <h5 class="modal-title text-dark fw-light">
                    <i class="icon-check me-2"></i>Cerrar Mantenimiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    @if(isset($openMaintenances) && $openMaintenances->count() > 1)
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Mantenimiento a cerrar *</label>
                            <select id="close_maintenance_id_input" class="form-select form-select-sm" required>
                                @foreach($openMaintenances as $om)
                                    @php
                                        $omCriteria = $om->auditValues->pluck('criterion.name')->filter()->join(', ') ?: ($om->category ?? 'Sin criterios');
                                    @endphp
                                    <option value="{{ $om->id }}">#{{ $om->id }} — {{ $omCriteria }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Documento de Cierre (PDF o Imagen) *</label>
                        <input type="file" id="closure_document_input" class="form-control form-control-sm" accept=".pdf,image/*" required>
                        <small class="text-muted">Formato: PDF, JPG, PNG. Máximo 10MB.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Comentario de Cierre</label>
                        <textarea id="closure_comment_input" class="form-control form-control-sm" rows="2"
                            placeholder="Nota opcional sobre el cierre..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white py-2 px-4">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info text-white px-4 fw-bold" onclick="submitCloseMaintenance()">Cerrar Mantenimiento</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos para los tabs del historial */
    #historyTabs .nav-link {
        color: #495057 !important;
        border: 1px solid transparent;
        background-color: #f8f9fa;
        margin-right: 4px;
        border-radius: 0.375rem 0.375rem 0 0;
    }
    
    #historyTabs .nav-link:hover {
        border-color: #dee2e6 #dee2e6 transparent;
        background-color: #e9ecef;
    }
    
    #historyTabs .nav-link.active {
        color: #0d6efd !important;
        background-color: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
    }
    
    /* Estilos para el modal de revisión */
    #uploadRevisionModal .form-control:focus,
    #editAuditModal .form-control:focus {
        border-color: #198754;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.15);
    }
    
    /* Validación estilo Orchid - mostrar invalid-feedback cuando is-invalid */
    .form-control.is-invalid ~ .invalid-feedback,
    .form-control.is-invalid + .invalid-feedback {
        display: block;
    }
    
    .form-control.is-invalid {
        border-color: #dc3545 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        padding-right: calc(1.5em + 0.75rem);
    }
    
    .form-control.is-invalid:focus {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
</style>
</style>
