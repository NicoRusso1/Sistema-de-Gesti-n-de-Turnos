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

    /*
    | n8n: recibe los recordatorios de turno y los manda por Telegram.
    | reminder_webhook_url: URL del Webhook del workflow "Recordatorio de turno por Telegram".
    | secret: valor compartido que viaja en el header X-N8N-Secret (en ambos sentidos).
    | telegram_bot_username: usuario del bot sin @, para armar el link de vinculación.
    */
    'n8n' => [
        'reminder_webhook_url' => env('N8N_REMINDER_WEBHOOK_URL'),
        'secret' => env('N8N_SECRET'),
        'telegram_bot_username' => env('TELEGRAM_BOT_USERNAME'),
        // chat_id que el seeder le asigna a paciente@hospital.test (para pruebas)
        'demo_chat_id' => env('TELEGRAM_DEMO_CHAT_ID'),
    ],

        'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'return_url' => env('MERCADOPAGO_RETURN_URL'),
        'sandbox' => env('MERCADOPAGO_SANDBOX', true),
    ],
];
