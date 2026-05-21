@php
    $year = (int) date('Y');
    $appVersion = config('app.panel_version', '1.0');
@endphp

<footer
    role="contentinfo"
    aria-label="Pie de panel administrativo"
    class="fi-panel-footer"
>
    <div class="fi-panel-footer__inner">
        <div class="fi-panel-footer__brand">
            <x-filament::icon
                icon="heroicon-s-building-office-2"
                class="fi-panel-footer__icon"
            />
            <p class="fi-panel-footer__copyright">
                <span class="fi-panel-footer__brand-name">TuDrGroup</span>
                <span class="fi-panel-footer__rights">© {{ $year }}. Todos los derechos reservados.</span>
            </p>
        </div>

        <div class="fi-panel-footer__meta">
            <p class="fi-panel-footer__credit">
                <x-filament::icon
                    icon="heroicon-s-code-bracket-square"
                    class="fi-panel-footer__icon fi-panel-footer__icon--muted"
                />
                <span>
                    Desarrollado por
                    <span class="fi-panel-footer__integracorp">IntegraCorp</span>
                </span>
            </p>

            <span
                class="fi-panel-footer__version"
                title="Versión de la plataforma"
            >
                v{{ $appVersion }}
            </span>
        </div>
    </div>
</footer>
