<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar Clave - Check Media - EFECTIMEDIOS</title>
    @include('auth.partials.auth-styles')
</head>

<body class="loginpage">

    <div class="loginpanel">
        <div class="logo">
            <img src="{{ asset('logo.png') }}" alt="Efectimedios" style="max-width: 220px;">
        </div>

        @if ($forced)
            <div class="alert-notice">
                Por seguridad debes definir una clave nueva antes de continuar.
            </div>
        @endif

        <form action="{{ route('password.forced.update') }}" method="POST">
            @csrf

            @error('current_password')
                <div class="alert-error">{{ $message }}</div>
            @enderror
            @error('password')
                <div class="alert-error">{{ $message }}</div>
            @enderror

            <div class="inputwrapper">
                <input type="password" name="current_password" placeholder="Clave Actual" required autofocus
                    autocomplete="current-password">
            </div>

            <div class="inputwrapper">
                <input type="password" name="password" placeholder="Nueva Clave" required
                    autocomplete="new-password">
            </div>

            <div class="inputwrapper">
                <input type="password" name="password_confirmation" placeholder="Confirmar Nueva Clave" required
                    autocomplete="new-password">
            </div>

            <div class="inputwrapper">
                <button type="submit">{{ __('Guardar Clave') }}</button>
            </div>
        </form>

        <form action="{{ route('platform.logout') }}" method="POST">
            @csrf
            <div class="inputwrapper">
                <button type="submit" style="background: transparent; border: 0; text-transform: none;">
                    {{ __('Cerrar sesión') }}
                </button>
            </div>
        </form>
    </div>

    <div class="loginfooter">
        <div style="margin-bottom: 10px;">
            <img src="{{ asset('logoefectimedios.png') }}" alt="Check Media" style="height: 40px;">
        </div>
        <p>&copy; {{ date('Y') }}. Check Media - EFECTIMEDIOS. Todos los derechos reservados.</p>
    </div>

</body>

</html>
