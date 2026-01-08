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
                            <th scope="col" style="width: 100px;">ACEPTABLE</th>
                            <th scope="col" style="width: 100px;">MALO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audit->values as $val)
                            <tr>
                                <td class="fw-bold text-dark">{{ $val->criterion->name }}</td>
                                <!-- Option 1: Bueno -->
                                <td class="text-center">
                                    <input type="radio" disabled {{ $val->value === 'good' ? 'checked' : '' }}
                                        class="form-check-input @if($val->value === 'good') border-success bg-success @else border-gray-300 @endif"
                                        style="opacity: 1;">
                                </td>
                                <!-- Option 2: Aceptable -->
                                <td class="text-center">
                                    <input type="radio" disabled {{ $val->value === 'acceptable' ? 'checked' : '' }}
                                        class="form-check-input @if($val->value === 'acceptable') border-warning bg-warning @else border-gray-300 @endif"
                                        style="opacity: 1;">
                                </td>
                                <!-- Option 3: Malo -->
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

            <!-- Observation -->
            @if($audit->observation)
                <div class="mb-3">
                    <h6 class="text-muted text-uppercase mb-2">Observaciones</h6>
                    <div class="p-2 bg-light border rounded">
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-secondary rounded-circle me-2"
                                style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="icon-user text-white"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-danger">{{ $audit->user->name ?? 'Auditor' }}</span>
                                <br>
                                <small class="text-muted" title="{{ $audit->created_at->format('d/m/Y g:i a') }}">
                                    {{ $audit->created_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>
                        <p class="mb-0">{{ $audit->observation }}</p>
                    </div>
                </div>
            @endif

            @if($audit->resolution_comment)
                <div class="mb-3">
                    <h6 class="text-success text-uppercase mb-2">✓ Revisión Cargada</h6>
                    <div class="p-2 bg-success bg-opacity-10 border border-success rounded">
                        <p class="mb-0 text-dark">{{ $audit->resolution_comment }}</p>
                        <small class="text-muted" title="{{ $audit->resolved_at->format('d/m/Y g:i a') }}">
                            Resuelto {{ $audit->resolved_at->diffForHumans() }}
                        </small>
                        @if($audit->resolution_photo_path)
                            <div class="mt-2">
                                <a href="{{ asset('storage/' . $audit->resolution_photo_path) }}" target="_blank"
                                    class="btn btn-sm btn-outline-success">Ver Foto de Reparación</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mb-3">
                <!-- Tercero Button (Orange) - Uses fetch API to submit -->
                <button type="button" class="btn text-white fw-bold px-4"
                    style="background-color: #FFA500; border: none;"
                    onclick="submitTercero()">
                    Tercero
                </button>

                <!-- Cargar Revisión (Green) - Triggers Modal -->
                <button type="button" class="btn btn-success text-white fw-bold px-4" data-bs-toggle="modal"
                    data-bs-target="#uploadRevisionModal">
                    Cargar Revisión
                </button>

                <!-- Editar (Green) -->
                <a href="{{ route('audit.form', ['external_code' => $audit->space->external_code]) }}" class="btn btn-success text-white fw-bold px-4">
                    Editar
                </a>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                function submitTercero() {
                    Swal.fire({
                        title: '¿Marcar como TERCERO?',
                        html: `
                            <div class="text-start">
                                <p class="mb-2">Al colocar estado <strong class="text-warning">TERCERO</strong> ocurrirá lo siguiente:</p>
                                <ul class="text-muted small">
                                    <li>Todos los reportes anteriores cambiarán a estado <strong class="text-success">OK</strong></li>
                                    <li>Este espacio quedará marcado como gestionado por terceros</li>
                                </ul>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#FFA500',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, marcar como Tercero',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading
                            Swal.fire({
                                title: 'Procesando...',
                                text: 'Actualizando estado del espacio',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Create and submit form dynamically
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '{{ url("/admin/audit/" . $audit->id . "/action/third-party") }}';
                            
                            var csrf = document.createElement('input');
                            csrf.type = 'hidden';
                            csrf.name = '_token';
                            csrf.value = '{{ csrf_token() }}';
                            form.appendChild(csrf);
                            
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }
            </script>

            <!-- Gallery -->
            <div class="border-top pt-3">
                <h5 class="text-black mb-3">Imágenes</h5>
                @if($audit->photos->count() > 0)
                    <div class="row g-2">
                        @foreach($audit->photos as $key => $photo)
                            <div class="col-6 col-md-3 col-lg-2">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#galleryModal"
                                   onclick="var myCarousel = document.getElementById('carouselGallery'); var carousel = bootstrap.Carousel.getOrCreateInstance(myCarousel); carousel.to({{ $key }}); return false;"
                                   class="d-block ratio ratio-4x3 border rounded overflow-hidden position-relative">
                                    <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Foto"
                                        class="w-100 h-100 object-fit-cover" style="object-fit: cover;">
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">No hay imágenes registradas.</p>
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

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-3" id="historyTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active text-dark fw-semibold" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                        <i class="icon-list me-1"></i> Actividad Reciente
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-dark fw-semibold" id="audits-tab" data-bs-toggle="tab" data-bs-target="#audits-history" type="button" role="tab">
                        <i class="icon-docs me-1"></i> Auditorías Anteriores
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="historyTabsContent">
                <!-- Activity Log Tab -->
                <div class="tab-pane fade show active" id="activity" role="tabpanel">
                    @if(isset($activityLogs) && $activityLogs->count() > 0)
                        <div class="timeline-container" style="max-height: 400px; overflow-y: auto;">
                            @foreach($activityLogs as $log)
                                <div class="d-flex mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        @php
                                            $iconClass = match($log->activity_type) {
                                                'audit_created' => 'bg-success',
                                                'audit_updated' => 'bg-info',
                                                'marked_third_party' => 'bg-warning',
                                                'resolution_uploaded' => 'bg-primary',
                                                'status_changed' => 'bg-secondary',
                                                default => 'bg-secondary',
                                            };
                                            $icon = match($log->activity_type) {
                                                'audit_created' => 'icon-plus',
                                                'audit_updated' => 'icon-pencil',
                                                'marked_third_party' => 'icon-people',
                                                'resolution_uploaded' => 'icon-camera',
                                                'status_changed' => 'icon-refresh',
                                                default => 'icon-circle',
                                            };
                                        @endphp
                                        <div class="rounded-circle {{ $iconClass }} d-flex align-items-center justify-content-center" 
                                             style="width: 36px; height: 36px;">
                                            <i class="{{ $icon }} text-white" style="font-size: 14px;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
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
                                                        @default
                                                            {{ ucfirst(str_replace('_', ' ', $log->activity_type)) }}
                                                    @endswitch
                                                </span>
                                                @if($log->week && $log->year)
                                                    <span class="badge bg-light text-dark ms-2">S{{ $log->week }}/{{ $log->year }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-muted mb-1 small">{{ $log->description }}</p>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted">
                                                <i class="icon-user me-1"></i>
                                                {{ $log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema') }}
                                            </small>
                                            <small class="text-muted ms-3" title="{{ $log->created_at->format('d/m/Y g:i a') }}">
                                                <i class="icon-clock me-1"></i>
                                                {{ $log->created_at->diffForHumans() }}
                                            </small>
                                        </div>
                                        @if($log->metadata)
                                            @if(isset($log->metadata['old_status']) && isset($log->metadata['new_status']))
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        Estado: 
                                                        <span class="badge {{ $log->metadata['old_status'] === 'bad' ? 'bg-danger' : ($log->metadata['old_status'] === 'acceptable' ? 'bg-warning' : 'bg-success') }}">
                                                            {{ ucfirst($log->metadata['old_status']) }}
                                                        </span>
                                                        →
                                                        <span class="badge {{ $log->metadata['new_status'] === 'bad' ? 'bg-danger' : ($log->metadata['new_status'] === 'acceptable' ? 'bg-warning' : 'bg-success') }}">
                                                            {{ ucfirst($log->metadata['new_status']) }}
                                                        </span>
                                                    </small>
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

                <!-- Previous Audits Tab -->
                <div class="tab-pane fade" id="audits-history" role="tabpanel">
                    @if(isset($allAudits) && $allAudits->count() > 0)
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Semana/Año</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Auditor</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allAudits as $histAudit)
                                        <tr class="{{ $histAudit->id === $audit->id ? 'table-active' : '' }}">
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    S{{ $histAudit->week }} / {{ $histAudit->year }}
                                                </span>
                                                @if($histAudit->id === $audit->id)
                                                    <span class="badge bg-primary ms-1">Actual</span>
                                                @endif
                                            </td>
                                            <td class="text-muted small" title="{{ $histAudit->audit_date ? $histAudit->audit_date->format('d/m/Y g:i a') : '' }}">
                                                {{ $histAudit->audit_date ? $histAudit->audit_date->diffForHumans() : '-' }}
                                            </td>
                                            <td>
                                                @if($histAudit->general_status === 'bad')
                                                    <span class="badge bg-danger">Malo</span>
                                                @elseif($histAudit->general_status === 'acceptable')
                                                    <span class="badge bg-warning text-dark">Aceptable</span>
                                                @else
                                                    <span class="badge bg-success">Bueno</span>
                                                @endif
                                                @if($histAudit->resolved_at)
                                                    <i class="icon-check text-success ms-1" title="Resuelto"></i>
                                                @endif
                                            </td>
                                            <td class="text-muted small">
                                                {{ $histAudit->user->name ?? 'N/A' }}
                                            </td>
                                            <td class="text-end">
                                                @if($histAudit->id !== $audit->id)
                                                    <a href="{{ route('platform.audit.detail', $histAudit) }}" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="icon-eye"></i> Ver
                                                    </a>
                                                @else
                                                    <span class="text-muted small">Vista actual</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="icon-docs" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-2 mb-0">No hay auditorías anteriores para este espacio.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Third Party Info (if applicable) -->
            @if($audit->space->is_third_party)
                <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                    <i class="icon-people me-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>Este espacio está marcado como TERCERO</strong>
                        @if($audit->space->third_party_modified_at)
                            <br>
                            <small class="text-muted" title="{{ \Carbon\Carbon::parse($audit->space->third_party_modified_at)->format('d/m/Y g:i a') }}">
                                Marcado {{ \Carbon\Carbon::parse($audit->space->third_party_modified_at)->diffForHumans() }}
                                @if($audit->space->third_party_user_id)
                                    por {{ \App\Models\User::find($audit->space->third_party_user_id)?->name ?? 'Usuario desconocido' }}
                                @endif
                            </small>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Cargar Revisión -->
<div class="modal fade" id="uploadRevisionModal" tabindex="-1" aria-labelledby="uploadRevisionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ url('/admin/audit/' . $audit->id . '/action/upload-revision') }}" method="POST"
            enctype="multipart/form-data" class="revision-form">
            @csrf
            <div class="modal-content border shadow-lg" style="border-radius: 0.5rem; overflow: hidden;">
                <div class="modal-header border-0 py-2 px-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <h6 class="modal-title text-white mb-0 fw-semibold" id="uploadRevisionModalLabel">
                        Cargar Revisión
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3">
                    <!-- Criterios con radio buttons -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold text-secondary">ESTADO DE CRITERIOS</span>
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" onclick="markAllGood()" style="font-size: 0.75rem;">
                                <i class="icon-check me-1"></i>Todos OK
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr class="text-center small text-secondary">
                                        <th class="text-start" style="width: 50%;">Criterio</th>
                                        <th style="width: 50px;">Bueno</th>
                                        <th style="width: 50px;">Regular</th>
                                        <th style="width: 50px;">Malo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($audit->values as $val)
                                        <tr>
                                            <td class="small text-dark align-middle">{{ $val->criterion->name }}</td>
                                            <td class="text-center align-middle">
                                                <input type="radio" name="criteria[{{ $val->id }}]" value="good"
                                                    class="form-check-input modal-radio {{ $val->value === 'good' ? 'border-success bg-success' : '' }}"
                                                    {{ $val->value === 'good' ? 'checked' : '' }}
                                                    onchange="updateRadioStyle(this, 'success')">
                                            </td>
                                            <td class="text-center align-middle">
                                                <input type="radio" name="criteria[{{ $val->id }}]" value="acceptable"
                                                    class="form-check-input modal-radio {{ $val->value === 'acceptable' ? 'border-warning bg-warning' : '' }}"
                                                    {{ $val->value === 'acceptable' ? 'checked' : '' }}
                                                    onchange="updateRadioStyle(this, 'warning')">
                                            </td>
                                            <td class="text-center align-middle">
                                                <input type="radio" name="criteria[{{ $val->id }}]" value="bad"
                                                    class="form-check-input modal-radio {{ $val->value === 'bad' ? 'border-danger bg-danger' : '' }}"
                                                    {{ $val->value === 'bad' ? 'checked' : '' }}
                                                    onchange="updateRadioStyle(this, 'danger')">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Foto -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">FOTO DE REVISIÓN *</label>
                        <input type="file" class="form-control form-control-sm" name="revision_photo" accept="image/*" required>
                    </div>
                    
                    <!-- Observación -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">OBSERVACIÓN</label>
                        <textarea class="form-control form-control-sm" name="revision_comment" rows="2"
                            placeholder="Descripción breve de la reparación..."></textarea>
                    </div>
                    
                    <!-- Emails -->
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary mb-1">NOTIFICAR A (OPCIONAL)</label>
                        <input type="text" class="form-control form-control-sm" name="client_emails" 
                            placeholder="correos separados por coma">
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success px-3">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function markAllGood() {
        document.querySelectorAll('#uploadRevisionModal input[type="radio"][value="good"]').forEach(radio => {
            radio.checked = true;
            updateRadioStyle(radio, 'success');
        });
    }
    
    function updateRadioStyle(radio, color) {
        // Clear all radios in the same row
        const row = radio.closest('tr');
        row.querySelectorAll('.modal-radio').forEach(r => {
            r.classList.remove('border-success', 'bg-success', 'border-warning', 'bg-warning', 'border-danger', 'bg-danger');
        });
        // Add style to selected
        radio.classList.add('border-' + color, 'bg-' + color);
    }
</script>

<!-- Modal Galería (Carousel Lightbox) -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center" style="min-height: 500px;">
                <div id="carouselGallery" class="carousel slide w-100 h-100" data-bs-interval="false">
                    <div class="carousel-inner h-100">
                        @foreach($audit->photos as $key => $photo)
                            <div class="carousel-item h-100 {{ $key == 0 ? 'active' : '' }}">
                                <div class="d-flex justify-content-center align-items-center" style="height: 80vh;">
                                    <img src="{{ asset('storage/' . $photo->file_path) }}" 
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
    #uploadRevisionModal .modal-content {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    #uploadRevisionModal .form-control {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        background-color: #fff;
    }
    
    #uploadRevisionModal .form-control:focus {
        border-color: #198754;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.15);
    }
    
    #uploadRevisionModal .table {
        margin-bottom: 0;
    }
    
    #uploadRevisionModal .table th,
    #uploadRevisionModal .table td {
        padding: 0.4rem;
        vertical-align: middle;
    }
    
    #uploadRevisionModal .modal-radio {
        width: 1.1rem;
        height: 1.1rem;
        cursor: pointer;
        opacity: 1 !important;
    }
    
    #uploadRevisionModal .modal-radio:not(:checked) {
        border: 2px solid #dee2e6;
        background-color: transparent;
    }
</style>