@php
    $loginLogo = asset($slide['data']['login_logo'] ?? 'image/logoNewTDG.png');
    $casaImage = asset($slide['data']['casa_image'] ?? 'image/storefront/tdg-casa-bg.jpg');
    $viajesImage = asset($slide['data']['viajes_image'] ?? 'image/storefront/tdg-viajes-bg.jpg');
@endphp

<div class="mkt-devices" aria-hidden="true">
    <figure class="pwa-device pwa-device--phone">
        <div class="pwa-device__frame pwa-device__frame--phone">
            <span class="pwa-device__side pwa-device__side--silent"></span>
            <span class="pwa-device__side pwa-device__side--volume"></span>
            <span class="pwa-device__side pwa-device__side--power"></span>
            <div class="pwa-device__screen pwa-device__screen--phone">
                <span class="pwa-device__island"></span>
                <div class="pwa-scale pwa-scale--phone">
                    @include('partials.presentation-marketing-login-screen', [
                        'variant' => 'phone',
                        'loginLogo' => $loginLogo,
                    ])
                </div>
                <span class="pwa-device__home"></span>
            </div>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['phone_caption'] ?? 'iPhone · Acceso' }}</figcaption>
    </figure>

    <figure class="pwa-device pwa-device--tablet">
        <div class="pwa-device__frame pwa-device__frame--tablet">
            <div class="pwa-device__screen pwa-device__screen--tablet">
                <span class="pwa-device__camera pwa-device__camera--tablet"></span>
                @include('partials.presentation-marketing-login-screen', [
                    'variant' => 'tablet',
                    'loginLogo' => $loginLogo,
                ])
                <span class="pwa-device__home pwa-device__home--tablet"></span>
            </div>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['tablet_caption'] ?? 'iPad · Acceso' }}</figcaption>
    </figure>

    <figure class="pwa-device portal-monitor">
        <div class="portal-monitor__body">
            <div class="portal-monitor__frame portal-monitor__frame--compact">
                <span class="portal-monitor__camera"></span>
                <div class="portal-monitor__screen portal-monitor__screen--compact">
                    <div class="portal-monitor__chrome">
                        <span class="portal-monitor__dots" aria-hidden="true">
                            <i></i><i></i><i></i>
                        </span>
                        <span class="portal-monitor__url">TDG Marketing</span>
                    </div>
                    <div class="portal-monitor__viewport portal-monitor__viewport--compact">
                        @include('partials.presentation-marketing-landing-screen', [
                            'logo' => $loginLogo,
                            'casaImage' => $casaImage,
                            'viajesImage' => $viajesImage,
                        ])
                    </div>
                </div>
            </div>
            <span class="portal-monitor__neck"></span>
            <span class="portal-monitor__base"></span>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['monitor_caption'] ?? 'PC · Landing' }}</figcaption>
    </figure>
</div>
