@php
    $logo = $logo ?? asset('image/logoNewTDG.png');
    $casaImage = $casaImage ?? asset('image/storefront/tdg-casa-bg.jpg');
    $viajesImage = $viajesImage ?? asset('image/storefront/tdg-viajes-bg.jpg');
@endphp

<div class="mkt-landing">
    <span class="mkt-landing__orb mkt-landing__orb--a" aria-hidden="true"></span>
    <span class="mkt-landing__orb mkt-landing__orb--b" aria-hidden="true"></span>
    <header class="mkt-landing__header">
        <img src="{{ $logo }}" alt="Tu Dr Group" class="mkt-landing__logo" width="180" height="44">
        <nav class="mkt-landing__nav">
            <span>Marcas</span>
            <span>Solución</span>
            <span>Capacidades</span>
            <strong>Acceso al panel</strong>
        </nav>
    </header>
    <div class="mkt-landing__hero">
        <div class="mkt-landing__stage">
            <p class="mkt-landing__eyebrow">Grupo · Salud · Viajes</p>
            <h3 class="mkt-landing__title">El marketing de <em>Tu Dr Group</em> para marcas que cuidan personas.</h3>
            <p class="mkt-landing__lead">TDG Marketing orquesta Tu Dr en Casa y Tu Dr en Viajes: asistencia médica en Venezuela y protección global para quien viaja.</p>
            <div class="mkt-landing__actions">
                <span class="mkt-landing__cta mkt-landing__cta--primary">Conocer las marcas</span>
                <span class="mkt-landing__cta">Marketing para tu red</span>
            </div>
        </div>
        <div class="mkt-landing__stack">
            <article class="mkt-landing__card mkt-landing__card--casa">
                <img src="{{ $casaImage }}" alt="" class="mkt-landing__card-bg" width="1400" height="933">
                <span class="mkt-landing__kicker">Marca de salud</span>
                <h4>Tu Dr en Casa</h4>
            </article>
            <article class="mkt-landing__card mkt-landing__card--viajes">
                <img src="{{ $viajesImage }}" alt="" class="mkt-landing__card-bg" width="1400" height="933">
                <span class="mkt-landing__kicker">Marca de viajes</span>
                <h4>Tu Dr en Viajes</h4>
            </article>
        </div>
    </div>
</div>
