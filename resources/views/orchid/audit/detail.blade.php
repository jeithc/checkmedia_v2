<div class="row">
    <!-- Left Column: Criteria Table -->
    <div class="col-md-8 mb-4">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h4 class="text-black mb-4">Detalle de Elementos</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th scope="col" class="text-start">Concepto</th>
                            <th scope="col" style="width: 100px;">Bueno</th>
                            <th scope="col" style="width: 100px;">Malo</th>
                            <th scope="col">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audit->values as $val)
                            <tr>
                                <td class="fw-bold text-dark">{{ $val->criterion->name }}</td>
                                <td class="text-center">
                                    @if($val->value === 'good')
                                        <div class="text-success fs-4">
                                            <i class="icon-check"></i> ●
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($val->value === 'bad')
                                        <div class="text-danger fs-4">
                                            <i class="icon-close"></i> ●
                                        </div>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    {{ $val->comment ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($audit->observation)
                <div class="alert alert-warning mt-3">
                    <strong>Observación General:</strong><br>
                    {{ $audit->observation }}
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Gallery -->
    <div class="col-md-4 mb-4">
        <div class="bg-white rounded shadow-sm p-4 h-100">
            <h4 class="text-black mb-4">Evidencias Fotográficas</h4>

            @if($audit->photos->count() > 0)
                <div class="row g-2">
                    @foreach($audit->photos as $photo)
                        <div class="col-6 col-lg-6">
                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank"
                                class="d-block ratio ratio-1x1 border rounded overflow-hidden position-relative">
                                <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Foto"
                                    class="w-100 h-100 object-fit-cover" style="object-fit: cover;">
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted p-5 bg-light rounded">
                    <i class="icon-camera fs-1 mb-3"></i>
                    <p>No hay fotos registradas.</p>
                </div>
            @endif
        </div>
    </div>
</div>