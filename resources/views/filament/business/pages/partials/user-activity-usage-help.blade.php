@php
    /*
     * Ayuda de «Uso real». El tooltip de Filament se dibuja fuera del contenedor,
     * así que no lo recorta el desplazamiento horizontal de las tablas. El clic se
     * detiene para no disparar el orden de la columna cuando va en un encabezado.
     */
    $idleMinutes = intdiv(\App\Support\UserActivity\UserActivityTracker::idleAfterSeconds(), 60);
    $usageHelp = '<div style="max-width: 280px; text-align: left; line-height: 1.5; text-transform: none; letter-spacing: normal; font-weight: 400;">'
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
