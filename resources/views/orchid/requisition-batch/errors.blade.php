@if(!empty($csvErrors))
    <div class="alert alert-danger">
        <h6 class="fw-bold mb-2">No se creó nada. Corrige estos errores y vuelve a intentar:</h6>
        <ul class="mb-0 ps-3">
            @foreach($csvErrors as $error)
                <li>
                    @if(!empty($error['line_number']))
                        <strong>Línea {{ $error['line_number'] }}:</strong>
                    @endif
                    {{ $error['message'] }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
