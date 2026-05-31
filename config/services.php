<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'advisual' => [
        'solicitante_uuid' => env('ADVISUAL_SOLICITANTE_UUID'),
        'crea_usuario' => env('ADVISUAL_CREA_USUARIO', 'CheckMedia'),
        'requisicion_estado' => env('ADVISUAL_REQUISICION_ESTADO', 1),
        'requisicion_tipo' => env('ADVISUAL_REQUISICION_TIPO', 2),
        'serial_prod' => env('ADVISUAL_SERIAL_PROD', 1),
        'serial_admin' => env('ADVISUAL_SERIAL_ADMIN', 0),
        'purchase_order_lookback_months' => env('ADVISUAL_PURCHASE_ORDER_LOOKBACK_MONTHS', 6),
        'purchase_order_schedule_time' => env('ADVISUAL_PURCHASE_ORDER_SCHEDULE_TIME', '06:30'),
        'requiprod_cantidad' => env('ADVISUAL_REQUIPROD_CANTIDAD', 1),
        'requiprod_can_pedida' => env('ADVISUAL_REQUIPROD_CAN_PEDIDA', 0),
        'requiprod_unidad_fallback' => env('ADVISUAL_REQUIPROD_UNIDAD_FALLBACK', 13),
    ],

    'legacy_photos_path' => env('LEGACY_PHOTOS_PATH', base_path('../auditoriaefectimedios.com/public_html')),

];
