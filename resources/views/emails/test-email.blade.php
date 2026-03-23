@extends('emails.layout')

@section('content')
<h1 style="color: #c60813; margin-top: 0;">¡Hola!</h1>
<p>Este es un correo de prueba enviado desde <strong>{{ config('app.name') }}</strong>.</p>
<p>Si estás viendo este mensaje con el diseño corporativo (logo y colores rojos), significa que la configuración de plantillas está funcionando correctamente.</p>

<div style="text-align: center;">
    <a href="{{ url('/') }}" class="btn">Ir al Sistema</a>
</div>
@endsection