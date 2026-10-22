<x-auth-layout title="Recuperar contraseña · Check Media · Efectimedios" heading="Recuperar contraseña"
    lead="Escribe el correo de tu cuenta y te enviaremos un enlace para restablecerla.">
    <form action="{{ route('password.email') }}" method="POST">
        @csrf

        <div class="login__field">
            <label class="login__label" for="email">Correo electrónico</label>
            <input class="login__input" id="email" type="email" name="email" value="{{ old('email') }}"
                placeholder="nombre@empresa.com" required autofocus autocomplete="email" autocapitalize="none"
                spellcheck="false"
                @error('email') aria-invalid="true" aria-describedby="login-error-email" @enderror>
        </div>

        <button class="login__submit login__submit--spaced" type="submit">Enviar enlace</button>
    </form>

    <p class="login__back">
        <a class="login__link" href="{{ route('platform.login') }}">Volver a iniciar sesión</a>
    </p>
</x-auth-layout>
