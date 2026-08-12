@php
    $sessionAccess = $access ?? \App\Support\PresentationHubGate::access();
    $sessionName = trim((string) ($sessionAccess['full_name'] ?? ''));
    $displayName = $sessionName !== '' ? $sessionName : 'Colaborador';
    $authenticatedAt = $sessionAccess['authenticated_at'] ?? null;
    $sessionTime = null;

    if (is_string($authenticatedAt) && $authenticatedAt !== '') {
        try {
            $sessionTime = \Illuminate\Support\Carbon::parse($authenticatedAt)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            $sessionTime = null;
        }
    }

    $brandLabel = $brandLabel ?? 'TUDRGROUP';
    $slideCount = $slideCount ?? 0;
@endphp

<header class="presentation-app-header header-glass fixed top-0 inset-x-0 z-50 border-b">
    <div class="presentation-app-header__row">
        <div class="presentation-app-header__brand min-w-0">
            <a href="{{ url('/dpto-tecnologia-sistemas') }}" class="logo-chip logo-chip--mark" title="Volver al hub">
                <img src="{{ asset('image/imagotipo.png') }}" alt="INTEGRACORP">
                <span class="presentation-app-header__brand-text">INTEGRACORP</span>
            </a>
            <div class="logo-chip presentation-app-header__partner" title="{{ $brandLabel }}">
                <img src="{{ asset('image/logoNewTDG.png') }}" alt="{{ $brandLabel }}">
            </div>
        </div>

        <div class="presentation-app-header__actions shrink-0">
            @if (is_array($sessionAccess))
                <div class="presentation-session" data-presentation-session title="{{ $displayName }}{{ $sessionTime ? ' · Desde '.$sessionTime : '' }}">
                    <div class="presentation-session__meta min-w-0">
                        <div class="presentation-session__label">Sesión</div>
                        <div class="presentation-session__name">{{ $displayName }}</div>
                        @if ($sessionTime)
                            <div class="presentation-session__time">{{ $sessionTime }}</div>
                        @endif
                    </div>
                    <a
                        href="{{ url('/dpto-tecnologia-sistemas/logout') }}"
                        class="presentation-session__logout"
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión"
                        data-presentation-logout
                    >
                        <span class="presentation-session__logout-text">Salir</span>
                        <svg class="presentation-session__logout-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3"/>
                        </svg>
                    </a>
                </div>
            @endif

            <button
                type="button"
                class="btn-glass presentation-app-header__icon-btn presentation-theme-toggle presentation-theme-toggle--header"
                data-presentation-theme-toggle
                title="Cambiar tema"
                aria-label="Cambiar tema claro u oscuro"
            >
                <svg data-theme-icon-sun width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
                <svg data-theme-icon-moon width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/>
                </svg>
                <span class="presentation-app-header__btn-label">Tema</span>
            </button>

            <button id="btn-overview" type="button" class="btn-glass presentation-app-header__icon-btn" title="Vista general (O)" aria-label="Vista general">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                </svg>
                <span class="presentation-app-header__btn-label">General</span>
            </button>

            <button id="btn-fullscreen" type="button" class="btn-glass presentation-app-header__icon-btn presentation-app-header__fullscreen" title="Pantalla completa (F)" aria-label="Pantalla completa">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                </svg>
                <span class="presentation-app-header__btn-label">Pantalla</span>
            </button>

            <span id="slide-counter" class="presentation-app-header__counter tabular-nums">1 / {{ $slideCount }}</span>
        </div>
    </div>
</header>
