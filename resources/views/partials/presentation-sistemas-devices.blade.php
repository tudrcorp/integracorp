@php
    $heroImage = asset($slide['data']['hero_image'] ?? 'image/presentaciones-sistemas-bg.png');
    $markImage = asset($slide['data']['mark_image'] ?? 'image/imagotipo.png');
@endphp

<div class="sys-devices" aria-hidden="true">
    <figure class="pwa-device pwa-device--phone">
        <div class="pwa-device__frame pwa-device__frame--phone">
            <span class="pwa-device__side pwa-device__side--silent"></span>
            <span class="pwa-device__side pwa-device__side--volume"></span>
            <span class="pwa-device__side pwa-device__side--power"></span>
            <div class="pwa-device__screen pwa-device__screen--phone">
                <span class="pwa-device__island"></span>
                <div class="pwa-scale pwa-scale--phone">
                    @include('partials.presentation-sistemas-hub-screen', [
                        'variant' => 'phone',
                        'heroImage' => $heroImage,
                        'markImage' => $markImage,
                    ])
                </div>
                <span class="pwa-device__home"></span>
            </div>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['phone_caption'] ?? 'iPhone · Identidad' }}</figcaption>
    </figure>

    <figure class="pwa-device portal-monitor">
        <div class="portal-monitor__body">
            <div class="portal-monitor__frame">
                <span class="portal-monitor__camera"></span>
                <div class="portal-monitor__screen">
                    <div class="portal-monitor__chrome">
                        <span class="portal-monitor__dots" aria-hidden="true">
                            <i></i><i></i><i></i>
                        </span>
                        <span class="portal-monitor__url">integracorp.tudrgroup.com/dpto-tecnologia-sistemas</span>
                    </div>
                    <div class="portal-monitor__viewport">
                        @include('partials.presentation-sistemas-hub-screen', [
                            'variant' => 'desktop',
                            'heroImage' => $heroImage,
                            'markImage' => $markImage,
                        ])
                    </div>
                </div>
            </div>
            <span class="portal-monitor__neck"></span>
            <span class="portal-monitor__base"></span>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['monitor_caption'] ?? 'PC · Panel' }}</figcaption>
    </figure>
</div>
