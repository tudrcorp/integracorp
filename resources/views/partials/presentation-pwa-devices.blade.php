@php
    $plans = $slide['data']['plans'] ?? [];
    $welcomeJpg = asset('image/storefront/welcome.jpg');
    $welcomeWebp = asset('image/storefront/welcome.webp');
    $logoWelcome = asset('image/logoNewPdf.png');
@endphp

<div class="pwa-devices" aria-hidden="true">
    <figure class="pwa-device pwa-device--phone">
        <div class="pwa-device__frame pwa-device__frame--phone">
            <span class="pwa-device__side pwa-device__side--silent"></span>
            <span class="pwa-device__side pwa-device__side--volume"></span>
            <span class="pwa-device__side pwa-device__side--power"></span>
            <div class="pwa-device__screen pwa-device__screen--phone">
                <span class="pwa-device__island"></span>
                <div class="pwa-scale pwa-scale--phone">
                    <div class="pwa-welcome">
                        <picture>
                            <source srcset="{{ $welcomeWebp }}" type="image/webp">
                            <img src="{{ $welcomeJpg }}" alt="" class="pwa-welcome__photo" width="390" height="844">
                        </picture>
                        <span class="pwa-welcome__shade"></span>
                        <header class="pwa-welcome__brand">
                            <img src="{{ $logoWelcome }}" alt="Tu Dr En Casa" width="168" height="43">
                        </header>
                        <section class="pwa-welcome__hero">
                            <p class="pwa-welcome__kicker">Asistencia médica</p>
                            <h3 class="pwa-welcome__title">Tu propia<br><em>asistencia médica</em></h3>
                        </section>
                        <div class="pwa-welcome__dock">
                            <span class="pwa-welcome__btn pwa-welcome__btn--plans">Entrar</span>
                            <span class="pwa-welcome__btn pwa-welcome__btn--google">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                                Continuar con Google
                            </span>
                            <p class="pwa-welcome__register"><span>¿No tienes cuenta?</span> <strong>Crear cuenta</strong></p>
                        </div>
                        <span class="pwa-device__home"></span>
                    </div>
                </div>
            </div>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['phone_caption'] ?? 'iPhone · Bienvenida' }}</figcaption>
    </figure>

    <figure class="pwa-device pwa-device--phone">
        <div class="pwa-device__frame pwa-device__frame--phone">
            <span class="pwa-device__side pwa-device__side--silent"></span>
            <span class="pwa-device__side pwa-device__side--volume"></span>
            <span class="pwa-device__side pwa-device__side--power"></span>
            <div class="pwa-device__screen pwa-device__screen--phone">
                <span class="pwa-device__island"></span>
                <div class="pwa-scale pwa-scale--phone">
                    <div class="pwa-plans">
                        <div class="pwa-plans__atmosphere"></div>
                        <header class="pwa-plans__header">
                            <div class="pwa-plans__brand">
                                <img src="{{ $logoWelcome }}" alt="" width="140" height="36">
                            </div>
                            <span class="pwa-plans__menu" aria-hidden="true">
                                <span></span><span></span><span></span>
                            </span>
                        </header>
                        <section class="pwa-plans__hero">
                            <p class="pwa-plans__kicker">Catálogo</p>
                            <h3 class="pwa-plans__title">Tu plan de asistencia, al alcance del pulgar</h3>
                        </section>
                        <div class="pwa-plans__list">
                            @foreach ($plans as $plan)
                                <article class="pwa-plan-card">
                                    <span class="pwa-plan-card__media">
                                        <picture>
                                            @if (! empty($plan['cover_webp']))
                                                <source srcset="{{ asset($plan['cover_webp']) }}" type="image/webp">
                                            @endif
                                            <img src="{{ asset($plan['cover']) }}" alt="" class="pwa-plan-card__photo" width="900" height="600">
                                        </picture>
                                        <span class="pwa-plan-card__shade"></span>
                                    </span>
                                    <span class="pwa-plan-card__body">
                                        <h4 class="pwa-plan-card__title">{{ $plan['title'] }}</h4>
                                        <p class="pwa-plan-card__promise">{{ $plan['promise'] }}</p>
                                        <div class="pwa-plan-card__meta">
                                            <strong>{{ $plan['price'] ?? 'Ver tarifas' }}</strong>
                                            <span>Ver plan →</span>
                                        </div>
                                    </span>
                                </article>
                            @endforeach
                        </div>
                        <span class="pwa-device__home"></span>
                    </div>
                </div>
            </div>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['plans_caption'] ?? 'iPhone · Planes' }}</figcaption>
    </figure>
</div>
