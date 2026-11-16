<?php

declare(strict_types=1);

/**
 * Notificaciones internas por Telegram (Bot API oficial).
 *
 * El bot se crea en @BotFather. En producción va el bot real y en local el de
 * pruebas: así nada de lo que se pruebe en desarrollo le llega al personal.
 * Las notificaciones a clientes siguen saliendo por UltraMsg (WhatsApp).
 */
return [
    /** Interruptor general. Apagado, ningún aviso sale por Telegram y todo usa los canales de siempre. */
    'enabled' => (bool) env('TELEGRAM_ENABLED', true),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /** Usuario del bot sin «@» (por ejemplo integracorpbot). Arma los enlaces t.me de vinculación. */
    'bot_username' => ltrim((string) env('TELEGRAM_BOT_USERNAME', ''), '@'),

    /** Clave que Telegram manda en cada llamada al webhook (cabecera X-Telegram-Bot-Api-Secret-Token). */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_base_url' => rtrim((string) env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'), '/'),

    /** Segundos de espera por respuesta de Telegram antes de tratar el envío como fallo transitorio. */
    'timeout_seconds' => (int) env('TELEGRAM_TIMEOUT_SECONDS', 10),

    /** Cola propia: los avisos no esperan detrás de respaldos, exportaciones ni PDFs. */
    'queue' => env('TELEGRAM_QUEUE', 'notifications'),

    /** Minutos de vida de un enlace de vinculación (un solo uso). */
    'link_token_minutes' => (int) env('TELEGRAM_LINK_TOKEN_MINUTES', 15),

    /** Dominio de correo que identifica al personal interno que puede vincularse por su cuenta. */
    'internal_email_domain' => mb_strtolower((string) env('TELEGRAM_INTERNAL_EMAIL_DOMAIN', 'tudrencasa.com')),

    /** Intentos por Telegram ante fallos transitorios antes de pasar al respaldo (WhatsApp / correo). */
    'max_attempts' => (int) env('TELEGRAM_MAX_ATTEMPTS', 4),

    /** Espera (segundos) entre intentos transitorios. */
    'backoff_seconds' => [10, 30, 90],

    /** Segundos que se guarda en caché el último chequeo de salud del bot. */
    'health_cache_seconds' => 90,
];
