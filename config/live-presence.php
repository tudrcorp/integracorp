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

    /**
     * Si algún día se activa el proxy de Cloudflare, CF-Connecting-IP y sus
     * encabezados de ubicación se aceptan solo desde rangos oficiales de
     * Cloudflare (ver security.cloudflare_ranges). Hoy producción es DNS only.
     */
    'trust_cloudflare_headers' => (bool) env('LIVE_PRESENCE_TRUST_CLOUDFLARE', true),

    /**
     * Enlace de solo lectura para la pantalla grande: /monitor/tv/{token}.
     * Vacío = la vista de TV está apagada. Generar con `php artisan live-presence:tv-token`.
     */
    'tv_token' => env('LIVE_MONITOR_TV_TOKEN'),

    /**
     * Colas que el monitor vigila, en el orden en que el worker debe atenderlas.
     * Con el driver `database` las colas que no estén aquí se descubren solas.
     * Una cola cuyo pendiente más viejo supera `stuck_after_minutes` se marca
     * atascada: casi siempre, ningún worker la está escuchando.
     */
    'queues' => [
        'names' => ['renovations', 'system', 'telemedicina', 'documents', 'certificates', 'renew', 'default'],
        'stuck_after_minutes' => (int) env('LIVE_PRESENCE_QUEUE_STUCK_MINUTES', 30),

        /** Segundos sin latido tras los que un worker se da por muerto (late cada 10 s). */
        'worker_alive_seconds' => 60,

        /** Una cola con trabajo y sin worker que la escuche se marca tras estos segundos. */
        'unattended_after_seconds' => 60,

        /** Un grupo de fallidos que no se repite hace estos días se recomienda eliminar. */
        'stale_group_days' => 7,

        /** Borrado automático diario de fallidos más viejos que estos días (0 = apagado). */
        'prune_failed_days' => (int) env('LIVE_PRESENCE_PRUNE_FAILED_DAYS', 30),

        /** Fallidos en 10 minutos a partir de los cuales se avisa por WhatsApp y correo. */
        'failed_spike_per_10_min' => 10,
    ],

    /**
     * Registro de errores del sistema (Negocios → Colas y errores).
     * Se agrupan por huella y se guardan en Redis sin cuerpos de petición ni secretos.
     */
    'errors' => [
        'enabled' => (bool) env('LIVE_PRESENCE_ERRORS_ENABLED', true),
        'retention_days' => 7,
        /** Horas que un error recién aparecido se muestra como «Nuevo». */
        'new_hours' => 24,
    ],

    /** Avisos por WhatsApp y correo que el vigilante envía como máximo por minuto; el resto se resume en uno. */
    'max_alerts_per_run' => 5,

    'security' => [
        /** IPs o rangos (CIDR) de confianza, separados por coma: nunca se marcan como amenaza. */
        'trusted_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('LIVE_SECURITY_TRUSTED_IPS', ''))))),

        /**
         * Rangos oficiales de Cloudflare (https://www.cloudflare.com/ips).
         * Solo si la conexión viene de uno de ellos se acepta CF-Connecting-IP.
         */
        'cloudflare_ranges' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ],

        'thresholds' => [
            /** Fuerza bruta desde una IP. */
            'failed_logins_per_ip' => 10,
            'failed_logins_per_ip_window' => 300,
            /** Bloqueo temporal de la cuenta atacada, aunque el atacante cambie de IP. */
            'account_lock_failures' => 10,
            'account_lock_window' => 900,
            'account_lock_minutes' => 15,
            /** Ataque distribuido: la misma cuenta atacada desde varias IPs. */
            'distributed_ips_per_account' => 3,
            'distributed_window' => 600,
            /** Relleno de credenciales: muchas cuentas distintas probadas desde una IP. */
            'emails_per_ip' => 5,
            'emails_per_ip_window' => 600,
            /** Escáner: ráfaga de 404 de una IP por minuto. */
            'not_found_per_ip_minute' => 30,
            /** Inundación: peticiones SIN sesión de una IP por minuto. */
            'requests_per_ip_minute' => 300,
            /** Sesión desbocada (rendimiento, no ataque): peticiones de un usuario con sesión por minuto. */
            'requests_per_user_minute' => 240,
            /** Ráfagas globales de 419 (CSRF) o 429 (límite) por minuto. */
            'rejections_per_minute' => 20,
            /** Semáforo por logins fallidos globales por minuto. */
            'failed_logins_amber_per_minute' => 5,
            'failed_logins_red_per_minute' => 20,
        ],

        /** Rutas que solo pide un escáner automático. */
        'scanner_paths' => [
            '.env', '.git', 'wp-login.php', 'wp-admin', 'wp-content', 'xmlrpc.php', 'phpmyadmin', 'pma',
            'vendor/phpunit', 'cgi-bin', '.aws', 'server-status', 'boaform', 'actuator', 'config.json',
            '.DS_Store', 'backup.sql', 'db.sql', 'shell.php', 'eval-stdin.php',
        ],

        /** User-Agent de herramientas y clientes automatizados. */
        'bot_agents' => [
            'curl', 'python-requests', 'python-urllib', 'aiohttp', 'go-http-client', 'wget', 'okhttp',
            'scrapy', 'headlesschrome', 'phantomjs', 'libwww-perl', 'java/', 'masscan', 'nmap', 'nikto',
            'sqlmap', 'zgrab', 'httpclient', 'postmanruntime', 'insomnia',
        ],

        /**
         * User-Agent de herramientas de ataque o escaneo. A diferencia de `bot_agents`
         * (curl, python…, que también usan integraciones legítimas), verlas es una
         * señal dura: la IP se califica «Amenaza confirmada».
         */
        'attack_agents' => [
            'sqlmap', 'nikto', 'masscan', 'nmap', 'zgrab', 'wpscan', 'nuclei', 'acunetix', 'dirbuster',
            'gobuster', 'ffuf', 'feroxbuster', 'hydra', 'netsparker', 'openvas', 'nessus', 'jaeles', 'whatweb',
        ],

        /** Días que una IP con login correcto cuenta como de uso legítimo. */
        'legitimate_ip_days' => 7,

        /** Días que dura la marca «Es legítima» puesta por un analista. */
        'dismiss_days' => 7,

        /** Minutos sin repetir el mismo aviso por WhatsApp y correo durante un ataque. */
        'alert_cooldown_minutes' => 30,
    ],

    /**
     * Base de ubicación por IP: DB-IP «IP to City Lite» (CC BY 4.0, uso comercial
     * permitido citando la fuente). MaxMind GeoLite2 prohíbe descargas desde
     * Venezuela por normas de EE. UU.; DB-IP no. Se actualiza sola cada mes con
     * `php artisan live-presence:geoip-update`, sin cuenta ni clave.
     */
    'geoip' => [
        'database' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/ip-city.mmdb')),
        'provider' => 'DB-IP',
        'provider_url' => 'https://db-ip.com',
        /** {month} = AAAA-MM. Si el mes en curso aún no está publicado se intenta el anterior. */
        'download_url' => 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz',
    ],
];
