<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333333;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f4f4f4;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .email-header {
            background-color: #c60813;
            /* Brand Red */
            padding: 20px;
            text-align: center;
        }

        .email-header img {
            max-height: 50px;
            max-width: 200px;
        }

        .email-body {
            padding: 30px;
            line-height: 1.6;
        }

        .email-footer {
            background-color: #333333;
            color: #ffffff;
            text-align: center;
            padding: 15px;
            font-size: 12px;
        }

        .btn {
            display: inline-block;
            background-color: #c60813;
            color: #ffffff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }

        .btn:hover {
            background-color: #a30c15;
            /* Darker Red */
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <!-- Assuming logo is accessible via public URL in production. 
                     For local testing, this might break in real email clients unless ngrok is used, 
                     but works in browser preview. -->
                <img src="{{ asset('logoefectimedios.png') }}" alt="{{ config('app.name') }}">
            </div>

            <!-- Body -->
            <div class="email-body">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="email-footer">
                &copy; {{ date('Y') }} Check Media - EFECTIMEDIOS.<br>Todos los derechos reservados.
            </div>
        </div>
    </div>
</body>

</html>