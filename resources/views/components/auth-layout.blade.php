{{-- Marco compartido de las pantallas de acceso (login, recuperar y restablecer contraseña):
     fondo de marca, logo, tarjeta con título, mensajes de estado/errores y pie. --}}
@props(['title', 'heading', 'lead' => null])
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#030712">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>

<body class="login">
    <div class="login__shell">
        <div class="login__glow" aria-hidden="true">
            <div class="login__glow-wash"></div>
        </div>

        <header class="login__brand">
            <x-brand-logo class="login__logo" />
            <p class="login__subtitle">Check Media</p>
        </header>

        <main class="login__card">
            <div class="login__header">
                <h1 class="login__title">{{ $heading }}</h1>
                @if ($lead)
                    <p class="login__lead">{{ $lead }}</p>
                @endif
            </div>

            @if (session('status'))
                <div class="login__alert login__alert--status" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="login__alert login__alert--error" role="alert" id="login-errors">
                    <ul class="login__errors">
                        @foreach ($errors->messages() as $field => $messages)
                            <li id="login-error-{{ $field }}">{{ implode(' ', $messages) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>

        <p class="login__footer">&copy; {{ date('Y') }} Efectimedios</p>
    </div>
</body>

</html>
