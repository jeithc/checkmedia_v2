<x-auth-layout title="Check Media · Efectimedios" heading="Iniciar sesión"
    lead="Ingresa con tu usuario para continuar.">
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
</x-auth-layout>
