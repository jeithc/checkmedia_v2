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
                    <small class="mb-0 text-muted">{{ $audit->space->city }}</small>
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
                                <td class="fw-bold">{{ $val->criterion->name }}</td>
                                <!-- Option 1: Bueno -->
                                <td class="text-center">
                                    <input type="radio" disabled {{ $val->value === 'good' ? 'checked' : '' }}
                                        class="form-check-input">
                                </td>
                                <!-- Option 2: Aceptable -->
                                <td class="text-center">
                                    <input type="radio" disabled {{ $val->value === 'acceptable' ? 'checked' : '' }}
                                        class="form-check-input">
                                </td>
                                <!-- Option 3: Malo -->
                                <td class="text-center">
                                    <input type="radio" disabled {{ $val->value === 'bad' ? 'checked' : '' }}
                                        class="form-check-input">
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
                                <small class="text-muted">el {{ $audit->created_at->format('Y-m-d') }}</small>
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
                        <small class="text-muted">Resuelto el {{ $audit->resolved_at->format('d/m/Y H:i') }}</small>
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
                <!-- Tercero Button (Orange) -->
                <form action="{{ route('platform.audit.action.third-party', $audit) }}" method="POST"
                    onsubmit="return confirm('Al colocar estado \'TERCERO\' todos los reportes anteriores cambiaran a estado \'OK\' ¿Desea continuar?');">
                    @csrf
                    <button type="submit" class="btn text-white fw-bold px-4"
                        style="background-color: #FFA500; border: none;">
                        Tercero
                    </button>
                </form>

                <!-- Cargar Revisión (Green) - Triggers Modal -->
                <button type="button" class="btn btn-success text-white fw-bold px-4" data-bs-toggle="modal"
                    data-bs-target="#uploadRevisionModal">
                    Cargar Revisión
                </button>

                <!-- Editar (Green) -->
                <a href="{{ route('audit.form', $audit->id) }}" class="btn btn-success text-white fw-bold px-4">
                    Editar
                </a>
            </div>

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
</div>

<!-- Modal Cargar Revisión -->
<div class="modal fade" id="uploadRevisionModal" tabindex="-1" aria-labelledby="uploadRevisionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('platform.audit.action.upload-revision', $audit) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadRevisionModalLabel">Cargar Revisión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="revisionDate" class="form-label">Fecha reparación</label>
                        <input type="text" class="form-control" id="revisionDate" value="{{ date('Y-m-d') }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="revisionComment" class="form-label">Observación</label>
                        <textarea class="form-control" id="revisionComment" name="revision_comment" rows="3"
                            placeholder="Agregar un comentario..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="revisionPhoto" class="form-label">Imagen(es)</label>
                        <input type="file" class="form-control" id="revisionPhoto" name="revision_photo"
                            accept="image/*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Enviar a cliente:</label>
                        <input type="text" class="form-control" placeholder="separar por (,)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success text-white">Enviar</button>
                </div>
            </div>
        </form>
    </div>
</div>

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