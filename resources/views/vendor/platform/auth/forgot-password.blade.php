<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar Clave - Check Media - EFECTIMEDIOS</title>
    <style>
        body.loginpage {
            background: #c60813;
            font-family: 'Roboto', sans-serif, Helvetica, Arial;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .loginpanel {
            width: 100%;
            max-width: 350px;
            text-align: center;
            padding: 20px;
        }

        .logo img {
            max-width: 100%;
            height: auto;
            margin-bottom: 30px;
        }

        .inputwrapper {
            margin-bottom: 15px;
        }

        .inputwrapper input {
            width: 100%;
            padding: 12px;
            border: 0;
            background: #fff;
            box-sizing: border-box;
            outline: none;
            font-size: 14px;
        }

        .inputwrapper button {
            width: 100%;
            padding: 12px;
            background: #dd0915;
            border: 1px solid #a30c15;
            color: #fff;
            text-transform: uppercase;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .inputwrapper button:hover {
            background: #a30c15;
        }

        .loginfooter {
            position: fixed;
            bottom: 20px;
            width: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 11px;
            font-family: Arial, sans-serif;
        }

        .signin-options {
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 12px;
            margin-top: 15px;
        }

        .signin-options a {
            color: #ddd;
            text-decoration: none;
        }

        .alert-error {
            color: white;
            background: #ea4145;
            padding: 10px;
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            color: white;
            background: #28a745;
            padding: 10px;
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .text-info {
            color: #fff;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }
    </style>
</head>

<body class="loginpage">

    <div class="loginpanel">
        <div class="logo">
            <a href="{{ route('platform.login') }}">
                <img src="{{ asset('logo.png') }}" alt="Efectimedios" style="max-width: 220px;">
            </a>
        </div>

        <div class="text-info">
            {{ __('¿Olvidaste tu contraseña? No hay problema. Simplemente déjanos saber tu dirección de correo electrónico y te enviaremos un enlace para restablecerla.') }}
        </div>

        @if (session('status'))
            <div class="alert-success">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf

            @error('email')
                <div class="alert-error">{{ $message }}</div>
            @enderror

            <div class="inputwrapper">
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Ingrese su Correo Electrónico"
                    required autofocus>
            </div>

            <div class="inputwrapper">
                <button type="submit">{{ __('Enviar enlace de recuperación') }}</button>
            </div>

            <div class="signin-options">
                <a href="{{ route('platform.login') }}">Volver al login</a>
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