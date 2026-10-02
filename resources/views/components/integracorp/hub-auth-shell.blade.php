@php
    $icAuthDateLabel = now()->locale('es')->translatedFormat('l j \d\e F');
@endphp

<div class="ic-auth">
    <aside class="ic-auth__hero" aria-hidden="true">
        <img
            class="ic-auth__hero-photo"
            src="{{ asset('image/hub-auth-hero.png') }}"
            alt=""
            decoding="async"
        />
        <div class="ic-auth__hero-gradient ic-auth__hero-gradient--horizontal"></div>
        <div class="ic-auth__hero-gradient ic-auth__hero-gradient--vertical"></div>
        <div class="ic-auth__hero-copy">
            <div class="ic-auth__hero-kicker">
                <span class="ic-auth__hero-kicker-line" aria-hidden="true"></span>
                <span>Acceso corporativo</span>
            </div>
            <div class="ic-auth__hero-title">Integracorp</div>
            <div class="ic-auth__hero-tags">
                <span>Salud y tecnología</span>
                <span class="ic-auth__hero-diamond" aria-hidden="true"></span>
                <span>Tecnología y salud</span>
            </div>
        </div>
    </aside>

    <div class="ic-auth__panel">
        <div class="ic-auth__panel-glow" aria-hidden="true"></div>

        <div class="ic-auth__panel-top">
            <p class="ic-auth__date">{{ $icAuthDateLabel }}</p>
            @include('integracorp.partials.hub-theme-toggle')
        </div>

        <div class="ic-auth__panel-inner">
            <div class="ic-auth__panel-body">
                {{ $slot }}
            </div>

            <p class="ic-auth__legal">
                © {{ date('Y') }} Integracorp · Tu Doctor Group
            </p>
        </div>
    </div>
</div>
