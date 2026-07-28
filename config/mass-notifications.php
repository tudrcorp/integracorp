<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Throttle entre envíos WhatsApp masivos
    |--------------------------------------------------------------------------
    |
    | Segundos mínimos entre un POST exitoso a UltraMsg y el siguiente.
    | Se aplica con un lock global para que varios workers no disparen a la vez.
    |
    */

    'whatsapp_throttle_seconds' => (int) env('MASS_NOTIFICATION_WHATSAPP_THROTTLE_SECONDS', 20),

    /*
    |--------------------------------------------------------------------------
    | Lock de canal WhatsApp
    |--------------------------------------------------------------------------
    |
    | El lock serializa el envío entre workers. seconds = TTL de seguridad si
    | el proceso muere. wait_seconds = cuánto espera el job por el lock antes
    | de reencolarse (0 = no bloquea el worker; reencola de inmediato).
    |
    */

    'whatsapp_lock_seconds' => (int) env('MASS_NOTIFICATION_WHATSAPP_LOCK_SECONDS', 90),

    'whatsapp_lock_wait_seconds' => (int) env('MASS_NOTIFICATION_WHATSAPP_LOCK_WAIT_SECONDS', 0),

    'whatsapp_lock_key' => 'mass-notification-whatsapp-send',

    'whatsapp_last_sent_cache_key' => 'mass-notification-whatsapp-last-sent-at',
];
