@extends('emails.layout')

@section('content')
    <h1 style="color: #c60813; margin-top: 0;">¡Hola!</h1>
    <p>Estás recibiendo este correo electrónico porque recibimos una solicitud de restablecimiento de contraseña para tu
        cuenta.</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $url }}" class="btn">Restablecer Clave</a>
    </div>

    <p>Este enlace de restablecimiento de contraseña caducará en
        {{ config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') }} minutos.</p>
    <p>Si no solicitaste un restablecimiento de contraseña, no es necesario realizar ninguna otra acción.</p>

    <p>Saludos,<br>
        Auditoría Checkmedia</p>

    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

    <p style="font-size: 11px; color: #777;">
        Si tienes problemas para hacer clic en el botón "Restablecer Clave", copia y pega la siguiente URL en tu navegador
        web:<br>
        <a href="{{ $url }}" style="color: #c60813; word-break: break-all;">{{ $url }}</a>
    </p>
@endsection