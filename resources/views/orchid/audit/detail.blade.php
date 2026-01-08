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
                <button type="button" class="btn btn-success text-white fw-bold px-4" data-bs-toggle="modal"
                    data-bs-target="#editAuditModal">
                    Editar
                </button>
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
                            form.action = '{{ url("/admin/audit-action/" . $audit->id . "/third-party") }}';
                            
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

            <!-- Observation (sin título) -->
            @if($audit->observation)
                <div class="mb-3 p-3 bg-light border rounded">
                    <div class="d-flex align-items-start">
                        <div class="bg-secondary bg-opacity-25 rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0"
                            style="width: 36px; height: 36px;">
                            <i class="icon-bubble text-secondary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <span class="fw-bold text-dark me-2">{{ $audit->user->name ?? 'Auditor' }}</span>
                                <small class="text-muted" title="{{ $audit->created_at->format('d/m/Y g:i a') }}">
                                    {{ $audit->created_at->diffForHumans() }}
                                </small>
                            </div>
                            <p class="mb-0 text-muted">{{ $audit->observation }}</p>
                        </div>
                    </div>
                </div>
            @endif


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
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <small class="text-muted">
                                                <i class="icon-user me-1"></i>
                                                {{ $log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema') }}
                                            </small>
                                            <small class="text-muted" title="{{ $log->created_at->format('d/m/Y g:i a') }}">
                                                <i class="icon-clock me-1"></i>
                                                {{ $log->created_at->diffForHumans() }}
                                            </small>
                                            
                                            {{-- Botón para ver foto si existe --}}
                                            @if(isset($log->metadata['photo_path']) && $log->metadata['photo_path'])
                                                <a href="{{ asset('storage/' . $log->metadata['photo_path']) }}" target="_blank"
                                                    class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.75rem;">
                                                    <i class="icon-camera me-1"></i> Ver Foto
                                                </a>
                                            @endif
                                        </div>
                                        
                                        @if($log->metadata)
                                            {{-- Cambio de estado --}}
                                            @if(isset($log->metadata['old_status']) && isset($log->metadata['new_status']))
                                                <div class="mt-2">
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
                                            
                                            {{-- Comentario/Observación --}}
                                            @if(isset($log->metadata['comment']) && $log->metadata['comment'])
                                                <div class="mt-2 p-2 bg-light rounded border-start border-3 border-secondary">
                                                    <small class="text-muted d-block mb-1"><i class="icon-bubble me-1"></i> Observación:</small>
                                                    <p class="mb-0 small text-dark">{{ $log->metadata['comment'] }}</p>
                                                </div>
                                            @endif
                                            @if(isset($log->metadata['added_comment']) && $log->metadata['added_comment'])
                                                <div class="mt-2 p-2 bg-light rounded border-start border-3 border-info">
                                                    <small class="text-muted d-block mb-1"><i class="icon-pencil me-1"></i> Nota:</small>
                                                    <p class="mb-0 small text-dark">{{ $log->metadata['added_comment'] }}</p>
                                                </div>
                                            @endif
                                            
                                            {{-- Cambios de criterios --}}
                                            @if(isset($log->metadata['criteria_changes']) && count($log->metadata['criteria_changes']) > 0)
                                                <div class="mt-2">
                                                    <small class="text-muted d-block mb-1"><i class="icon-list me-1"></i> Criterios modificados:</small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($log->metadata['criteria_changes'] as $change)
                                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                                {{ $change['criterion'] }}:
                                                                <span class="{{ $change['old'] === 'bad' ? 'text-danger' : ($change['old'] === 'acceptable' ? 'text-warning' : 'text-success') }}">{{ ucfirst($change['old']) }}</span>
                                                                →
                                                                <span class="{{ $change['new'] === 'bad' ? 'text-danger' : ($change['new'] === 'acceptable' ? 'text-warning' : 'text-success') }}">{{ ucfirst($change['new']) }}</span>
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
                                            <th class="fw-normal" style="width: 60px;">Regular</th>
                                            <th class="fw-normal" style="width: 60px;">Malo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach($audit->values as $val)
                                            <tr class="border-bottom-light">
                                                <td class="ps-3 py-2 text-dark small">{{ $val->criterion->name }}</td>
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
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="acceptable"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'acceptable' ? 'bg-warning border-warning' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'acceptable' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'warning')">
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
                        <!-- Foto -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Foto de Revisión *</label>
                            <input type="file" class="form-control form-control-sm bg-white" name="revision_photo" accept="image/*" required>
                        </div>
                        
                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Observación</label>
                            <textarea class="form-control form-control-sm bg-white" name="revision_comment" rows="2"
                                placeholder="Descripción breve..."></textarea>
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
                                            <th class="fw-normal" style="width: 60px;">Regular</th>
                                            <th class="fw-normal" style="width: 60px;">Malo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach($audit->values as $val)
                                            <tr class="border-bottom-light">
                                                <td class="ps-3 py-2 text-dark small">{{ $val->criterion->name }}</td>
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
                                                        <input type="radio" name="criteria[{{ $val->id }}]" value="acceptable"
                                                            class="form-check-input shadow-none cursor-pointer {{ $val->value === 'acceptable' ? 'bg-warning border-warning' : '' }}"
                                                            style="width: 1.2em; height: 1.2em;"
                                                            {{ $val->value === 'acceptable' ? 'checked' : '' }}
                                                            onchange="updateRadioStyle(this, 'warning')">
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
                            <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Nota de Edición (Opcional)</label>
                            <textarea class="form-control form-control-sm bg-white" name="revision_comment" rows="2"
                                placeholder="Agregar nota sobre el cambio..."></textarea>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mover los modales al final del body para evitar conflictos de formularios anidados
        ['uploadRevisionModal', 'editAuditModal'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    });

    function submitRevisionForm() {
        const modal = document.getElementById('uploadRevisionModal');
        const fileInput = modal.querySelector('input[name="revision_photo"]');
        const commentInput = modal.querySelector('textarea[name="revision_comment"]');
        const emailsInput = modal.querySelector('input[name="client_emails"]');
        
        // Validación
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta imagen',
                text: 'Por favor selecciona una foto de la revisión.',
                confirmButtonColor: '#198754'
            });
            return;
        }

        // Construir FormData
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('revision_photo', fileInput.files[0]);
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
                window.location.href = response.url;
            } else if (response.ok) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'La revisión se ha cargado correctamente.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                return response.text().then(text => {
                    // Try to parse JSON error if available
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
        const commentInput = modal.querySelector('textarea[name="revision_comment"]');
        
        // Construir FormData
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('revision_comment', commentInput.value || '');
        
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
            if (response.redirected) window.location.href = response.url;
            else if (response.ok) {
                 Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'Los datos han sido guardados.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => window.location.reload());
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
    #uploadRevisionModal .form-control:focus {
        border-color: #198754;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.15);
    }
</style>
</style>