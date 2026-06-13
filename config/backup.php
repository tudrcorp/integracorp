<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notificación WhatsApp
    |--------------------------------------------------------------------------
    |
    | Número que recibe el resumen y el archivo .sql al finalizar el respaldo.
    |
    */

    'notification_phone' => env('DB_BACKUP_NOTIFICATION_PHONE', '04127018390'),

    /*
    | Teléfonos: ver config/scheduled-notifications.php (SCHEDULED_NOTIFICATION_PHONES).
    */

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento
    |--------------------------------------------------------------------------
    */

    'directory' => 'database-backups',

    'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Límite de tamaño para adjuntar por WhatsApp (MB)
    |--------------------------------------------------------------------------
    */

    'max_whatsapp_attachment_mb' => (int) env('DB_BACKUP_MAX_WHATSAPP_MB', 50),

];
