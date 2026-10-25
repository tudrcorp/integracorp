{{--
    Dashboard de Operaciones: tokens de color (paleta validada, claro y oscuro),
    animaciones y modo presentación. Se inserta solo en esta página.
--}}
<style>
    :root {
        --ops-series1: #2a78d6;
        --ops-series2: #eb6834;
        --ops-muted: #898781;
        --ops-good-ink: #006300;
        --ops-bad-ink: #b42f2f;
        --ops-good: #0ca30c;
        --ops-warning: #fab219;
        --ops-critical: #d03b3b;
        --ops-violet: #6250d6;
        --ops-track: #ecebe6;
        --ops-heat-0: transparent;
        --ops-heat-1: #cde2fb;
        --ops-heat-2: #9ec5f4;
        --ops-heat-3: #6da7ec;
        --ops-heat-4: #3987e5;
        --ops-heat-5: #256abf;
        --ops-heat-6: #184f95;
        --ops-heat-7: #0d366b;
    }

    :root.dark {
        --ops-series1: #3987e5;
        --ops-series2: #d95926;
        --ops-good-ink: #0ca30c;
        --ops-bad-ink: #e66767;
        --ops-violet: #9085e9;
        --ops-track: #2c2c2a;
        --ops-heat-1: #0d366b;
        --ops-heat-2: #184f95;
        --ops-heat-3: #256abf;
        --ops-heat-4: #3987e5;
        --ops-heat-5: #6da7ec;
        --ops-heat-6: #9ec5f4;
        --ops-heat-7: #cde2fb;
    }

    /* Cada tarjeta ocupa toda la altura de su fila: sin huecos debajo de las más bajas. */
    .fi-wi-widget > .fi-section {
        height: 100%;
    }

    /* Esqueleto de carga: un brillo recorre la tarjeta mientras llega el widget. */
    .ops-skeleton::after {
        content: '';
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent);
        animation: ops-shimmer 1.4s ease-in-out infinite;
    }

    :root.dark .ops-skeleton::after {
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .06), transparent);
    }

    @keyframes ops-shimmer {
        to { transform: translateX(100%); }
    }

    /* Al llegar, el widget aparece con un fundido corto en lugar de un salto. */
    .fi-wi-widget {
        animation: ops-fade-in 420ms cubic-bezier(.22, 1, .36, 1) both;
    }

    @keyframes ops-fade-in {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: none; }
    }

    .ops-reveal {
        transition: opacity 700ms cubic-bezier(.22, 1, .36, 1), transform 700ms cubic-bezier(.22, 1, .36, 1);
    }

    .ops-bar-fill {
        transition: width 900ms cubic-bezier(.22, 1, .36, 1);
    }

    .ops-heat-cell {
        transition: transform 150ms ease, box-shadow 150ms ease;
    }

    .ops-heat-cell:hover {
        transform: scale(1.08);
        box-shadow: 0 0 0 2px var(--ops-series1);
        z-index: 1;
    }

    @media (prefers-reduced-motion: reduce) {
        .ops-reveal, .ops-bar-fill, .ops-heat-cell { transition: none !important; }
        .ops-skeleton::after, .fi-wi-widget { animation: none !important; }
    }

    /* Modo presentación: pantalla completa, sin menú, tipografía mayor. */
    html.ops-presenting .fi-sidebar,
    html.ops-presenting .fi-topbar,
    html.ops-presenting .fi-sidebar-close-overlay,
    html.ops-presenting .fi-page-header-main-ctn .fi-ac,
    html.ops-presenting .fi-page > .fi-page-header-widgets,
    html.ops-presenting [wire\:key="business-helpdesk-tickets-ticker-root"],
    html.ops-presenting .tickets-ticker-wrap {
        display: none !important;
    }

    html.ops-presenting .fi-main-ctn {
        margin-inline-start: 0 !important;
        padding-inline-start: 0 !important;
    }

    html.ops-presenting .fi-main {
        max-width: none !important;
        padding: 1.5rem 2rem !important;
    }

    html.ops-presenting body {
        font-size: 1.06rem;
    }

    html.ops-presenting .ops-presentation-only {
        display: flex !important;
    }
</style>

<div
    x-data="{
        presenting: false,
        timer: null,
        clock: '',
        clockTimer: null,
        init() {
            window.opsDashboardPresentation = this;
            this.tickClock();
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement && this.presenting) { this.stop(); }
            });
        },
        tickClock() {
            this.clock = new Intl.DateTimeFormat('es-VE', { dateStyle: 'long', timeStyle: 'short' }).format(new Date());
        },
        toggle() { this.presenting ? this.stop() : this.start(); },
        start() {
            this.presenting = true;
            document.documentElement.classList.add('ops-presenting');
            document.documentElement.requestFullscreen?.().catch(() => {});
            this.timer = setInterval(() => Livewire.dispatch('operations-dashboard-refresh'), 120000);
            this.clockTimer = setInterval(() => this.tickClock(), 30000);
        },
        stop() {
            this.presenting = false;
            document.documentElement.classList.remove('ops-presenting');
            clearInterval(this.timer);
            clearInterval(this.clockTimer);
            if (document.fullscreenElement) { document.exitFullscreen?.().catch(() => {}); }
        },
        destroy() { this.stop(); },
    }"
    x-on:keydown.escape.window="presenting && stop()"
    class="ops-dash"
>
    @if ($branding ?? true)
    <div
        class="ops-presentation-only mb-4 hidden items-center justify-between gap-4 rounded-2xl bg-white px-5 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
        x-cloak
    >
        <div class="flex items-center gap-3">
            <img src="{{ asset('image/logoNewTDG.png') }}" alt="TDG" class="h-8 dark:hidden">
            <img src="{{ asset('image/logoTDG.png') }}" alt="TDG" class="hidden h-8 dark:block">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $brandingLabel ?? 'Operaciones · Comportamiento del servicio' }}</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500 dark:text-gray-400" x-text="clock"></span>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-pointing-in" x-on:click="stop()">
                Salir (Esc)
            </x-filament::button>
        </div>
    </div>
    @else
        {{-- Proveedor: su encabezado ya identifica la pantalla; solo un botón discreto para salir. --}}
        <div class="ops-presentation-only fixed end-4 top-4 z-40 hidden" x-cloak>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-pointing-in" x-on:click="stop()">
                Salir (Esc)
            </x-filament::button>
        </div>
    @endif
</div>
