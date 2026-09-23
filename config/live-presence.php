<?php

declare(strict_types=1);

/**
 * Monitor de actividad en vivo (panel Negocios → Monitor en vivo).
 *
 * Todo vive en Redis en producción y en la caché por defecto en desarrollo
 * (base de datos): nada se escribe en tablas de MySQL propias y los datos
 * vencen solos.
 */
return [
    'enabled' => (bool) env('LIVE_PRESENCE_ENABLED', true),

    /** Únicos correos que pueden abrir el monitor. Separados por coma. */
    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('LIVE_PRESENCE_ALLOWED_EMAILS', 'gcamacho@tudrencasa.com')),
    ))),

    /**
     * auto: Redis si la caché o la sesión de la aplicación usan Redis; si no, la caché por defecto.
     * redis | cache: forzar uno de los dos.
     */
    'store' => env('LIVE_PRESENCE_STORE', 'auto'),

    'redis_connection' => env('LIVE_PRESENCE_REDIS_CONNECTION', 'default'),

    /** Store de caché para el modo `cache`. null = el store por defecto. */
    'cache_store' => env('LIVE_PRESENCE_CACHE_STORE'),

    /** Segundos sin actividad tras los que una sesión deja de verse como conectada. */
    'online_window' => 90,

    /** Segundos que vive el registro de una sesión sin latidos. */
    'session_ttl' => 300,

    /** Cada cuánto late el navegador con la pestaña visible y oculta. */
    'heartbeat_seconds' => 15,
    'hidden_heartbeat_seconds' => 60,

    /** Acciones que se guardan por usuario y cuánto duran. */
    'timeline_size' => 50,
    'timeline_ttl' => 86400,

    /** Muestras de tiempo de respuesta para el rendimiento del sistema. */
    'performance_samples' => 500,

    /** Producción está detrás de Cloudflare: la IP real y la ubicación llegan en sus encabezados. */
    'trust_cloudflare_headers' => (bool) env('LIVE_PRESENCE_TRUST_CLOUDFLARE', true),

    'geoip' => [
        'database' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'download_url' => 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz',
    ],
];
