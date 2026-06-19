<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Inicio del seguimiento de cotizaciones individuales
    |--------------------------------------------------------------------------
    |
    | Las tareas de seguimiento (3, 5, 7, 9 y 12 días) solo se ejecutan a
    | partir de esta fecha (inclusive). Formato: Y-m-d
    |
    */

    'follow_up_scheduling_start_date' => env('INDIVIDUAL_QUOTE_FOLLOW_UP_START_DATE', '2026-06-18'),

];
