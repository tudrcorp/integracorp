<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Teléfonos WhatsApp para tareas programadas
    |--------------------------------------------------------------------------
    |
    | Resúmenes de cumpleaños, exportaciones, respaldos, cobranzas, etc.
    | Separar varios números con coma en SCHEDULED_NOTIFICATION_PHONES.
    |
    */

    'phones' => array_values(array_filter(array_map(
        static fn (string $phone): string => trim($phone),
        explode(',', (string) env('SCHEDULED_NOTIFICATION_PHONES', '04127018390,04143027250')),
    ))),

];
