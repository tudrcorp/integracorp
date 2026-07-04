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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Clave usada en mapas de proveedores (Filament) y vistas públicas.
     * APIs requeridas en Google Cloud para la misma clave:
     * - Maps JavaScript API
     * - Places API
     * - Directions API (rutas y tiempos de recorrido)
     * Si la clave tiene "Restricciones de API", incluya las tres en la lista permitida.
     */
    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'default_lat' => (float) env('GOOGLE_MAPS_DEFAULT_LAT', 10.4806),
        'default_lng' => (float) env('GOOGLE_MAPS_DEFAULT_LNG', -66.9036),
    ],

    'chat_agent_registration' => [
        'portal_login_url' => env('CHAT_AGENT_PORTAL_URL', 'https://integracorp.tudrgroup.com/agents/login'),
        'business_whatsapp_phone' => env('CHAT_BUSINESS_WHATSAPP_PHONE', '584127018390'),
        'default_owner_code' => env('CHAT_AGENT_DEFAULT_OWNER_CODE', 'TDG-100'),
    ],

    'chat_agency_master_registration' => [
        'portal_login_url' => env('CHAT_AGENCY_MASTER_PORTAL_URL', env('APP_URL', 'https://integracorp.test').'/master/login'),
    ],

    'chat_agency_general_registration' => [
        'portal_login_url' => env('CHAT_AGENCY_GENERAL_PORTAL_URL', env('APP_URL', 'https://integracorp.test').'/general/login'),
    ],

    'chat_individual_quote' => [
        'default_owner_code' => env('CHAT_INDIVIDUAL_QUOTE_DEFAULT_OWNER_CODE', 'TDG-100'),
        'created_by_label' => env('CHAT_INDIVIDUAL_QUOTE_CREATED_BY', 'CHAT PUBLICO'),
        'placeholder_email' => env('CHAT_INDIVIDUAL_QUOTE_PLACEHOLDER_EMAIL', 'cotizacion-chat@integracorp.local'),
        'placeholder_phone' => env('CHAT_INDIVIDUAL_QUOTE_PLACEHOLDER_PHONE', '00000000000'),
    ],

    'company_associate_inclusion' => [
        'public_url' => env('COMPANY_ASSOCIATE_INCLUSION_PUBLIC_URL'),
    ],

];
