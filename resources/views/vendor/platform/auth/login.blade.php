<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#030712">
    <title>Check Media · Efectimedios</title>
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
                <h1 class="login__title">Iniciar sesión</h1>
                <p class="login__lead">Ingresa con tu usuario para continuar.</p>
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

            <form action="{{ route('platform.login.auth') }}" method="POST">
                @csrf

                <div class="login__field">
                    <label class="login__label" for="username">Usuario</label>
                    <input class="login__input" id="username" type="text" name="username"
                        value="{{ old('username') }}" placeholder="Ingresa tu usuario" required autofocus
                        autocomplete="username" autocapitalize="none" spellcheck="false"
                        @error('username') aria-invalid="true" aria-describedby="login-error-username" @enderror>
                </div>

                <div class="login__field">
                    <label class="login__label" for="password">Contraseña</label>
                    <input class="login__input" id="password" type="password" name="password" required
                        autocomplete="current-password"
                        @error('password') aria-invalid="true" aria-describedby="login-error-password" @enderror>
                </div>

                <div class="login__options">
                    <label class="login__remember" for="remember">
                        <input id="remember" type="checkbox" name="remember">
                        <span>Recordarme</span>
                    </label>

                    <a class="login__link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                </div>

                <button class="login__submit" type="submit">Ingresar</button>
            </form>
        </main>

        <p class="login__footer">&copy; {{ date('Y') }} Efectimedios</p>
    </div>
</body>

</html>
