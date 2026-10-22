<x-auth-layout title="Nueva contraseña · Check Media · Efectimedios" heading="Nueva contraseña"
    lead="Elige una contraseña nueva para tu cuenta.">
    <form action="{{ route('password.update') }}" method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="login__field">
            <label class="login__label" for="email">Correo electrónico</label>
            <input class="login__input" id="email" type="email" name="email"
                value="{{ old('email', $request->email) }}" required autocomplete="email" autocapitalize="none"
                spellcheck="false"
                @error('email') aria-invalid="true" aria-describedby="login-error-email" @enderror>
        </div>

        <div class="login__field">
            <label class="login__label" for="password">Nueva contraseña</label>
            <input class="login__input" id="password" type="password" name="password" required autofocus
                autocomplete="new-password"
                @error('password') aria-invalid="true" aria-describedby="password-hint login-error-password" @else aria-describedby="password-hint" @enderror>
            <p class="login__hint" id="password-hint">Mínimo 8 caracteres.</p>
        </div>

        <div class="login__field">
            <label class="login__label" for="password_confirmation">Confirmar contraseña</label>
            <input class="login__input" id="password_confirmation" type="password" name="password_confirmation"
                required autocomplete="new-password">
        </div>

        <button class="login__submit login__submit--spaced" type="submit">Guardar contraseña</button>
    </form>

    <p class="login__back">
        @if ($errors->any())
            <a class="login__link" href="{{ route('password.request') }}">Solicitar un nuevo enlace</a>
        @endif
        <a class="login__link" href="{{ route('platform.login') }}">Volver a iniciar sesión</a>
    </p>
</x-auth-layout>
