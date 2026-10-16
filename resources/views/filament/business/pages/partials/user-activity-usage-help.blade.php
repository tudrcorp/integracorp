@php
    /*
     * Ayuda de «Uso real». El tooltip de Filament se dibuja fuera del contenedor,
     * así que no lo recorta el desplazamiento horizontal de las tablas. El clic se
     * detiene para no disparar el orden de la columna cuando va en un encabezado.
     */
    $idleMinutes = intdiv(\App\Support\UserActivity\UserActivityTracker::idleAfterSeconds(), 60);
    $workdayHelp = \App\Support\UserActivity\Workday\WorkdayConfig::enabled()
        ? '<strong>Uso de jornada = activo en jornada ÷ jornada transcurrida</strong><br>'
            .'La jornada es de '.\App\Support\UserActivity\UserActivityClock::formatMinute(\App\Support\UserActivity\Workday\WorkdayConfig::workStart()).' a '.\App\Support\UserActivity\UserActivityClock::formatMinute(\App\Support\UserActivity\Workday\WorkdayConfig::workEnd()).', lunes a viernes. El almuerzo declarado (hasta '.\App\Support\UserActivity\Workday\WorkdayConfig::lunchMinutes().' min) no cuenta; sin declararlo, esa hora sí es jornada. Lo que falta del día no cuenta todavía.<br><br>'
        : '';
    $usageHelp = '<div style="max-width: 300px; text-align: left; line-height: 1.5; text-transform: none; letter-spacing: normal; font-weight: 400;">'
        .$workdayHelp
        .'<strong>Uso real = minutos activos ÷ minutos conectados</strong><br>'
        .'<strong>Activo:</strong> tecleó, movió el mouse, tocó o desplazó la pantalla en los últimos '.$idleMinutes.' min.<br>'
        .'<strong>Conectado:</strong> activo + inactivo (a la vista sin tocarlo) + en otra pestaña.<br>'
        .'Verde ≥ 70 % · Ámbar 40–69 % · Rojo &lt; 40 %.<br>'
        .'<em>Mide el uso del sistema, no la productividad.</em>'
        .'</div>';
@endphp
<button
    type="button"
    class="uam-help-btn"
    aria-label="¿Cómo se calcula el uso real?"
    x-data
    x-on:click.stop
    x-tooltip="{ content: @js($usageHelp), theme: $store.theme, allowHTML: true }"
>?</button>
