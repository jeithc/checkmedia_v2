<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check Media - EFECTIMEDIOS</title>
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
        /* Specific fix: Don't let global input styling break the checkbox */
        .inputwrapper input[type="checkbox"] {
            width: auto;
            margin: 0;
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
        
        /* Flex container for the bottom row */
        .signin-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            font-size: 12px;
            margin-top: 15px;
        }
        .signin-options a { color: #ddd; text-decoration: none; }
        .signin-options label { 
            display: flex; 
            align-items: center; 
            cursor: pointer; 
            gap: 5px; /* Space between checkbox and text */
        }
        .signin-options input[type="checkbox"] {
             margin: 0; 
             width: auto;
        }

        .alert-error {
            color: white;
            background: #ea4145;
            padding: 10px;
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body class="loginpage">

<div class="loginpanel">
    <div class="logo">
        <img src="{{ asset('logo.png') }}" alt="Efectimedios" style="max-width: 220px;">
    </div>

    <form action="{{ route('platform.login.auth') }}" method="POST">
        @csrf

        @error('username')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('password')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="inputwrapper">
            <input type="text" name="username" value="{{ old('username') }}" placeholder="Ingrese su Usuario" required autofocus>
        </div>
        <div class="inputwrapper">
            <input type="password" name="password" placeholder="Ingrese su Clave" required>
        </div>

        <div class="inputwrapper">
            <button type="submit">ENTRAR</button>
        </div>

        <div class="signin-options">
            <label>
                <input type="checkbox" name="remember"> 
                Recordarme
            </label>
            <a href="#">No recuerda su Clave?</a>
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