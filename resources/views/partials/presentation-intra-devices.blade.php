@php
    $logo = asset($slide['data']['login_logo'] ?? 'image/logoNewTDG.png');
@endphp

<div class="intra-devices" aria-hidden="true">
    <figure class="pwa-device portal-monitor">
        <div class="portal-monitor__body">
            <div class="portal-monitor__frame portal-monitor__frame--wide">
                <span class="portal-monitor__camera"></span>
                <div class="portal-monitor__screen portal-monitor__screen--wide">
                    <div class="portal-monitor__chrome">
                        <span class="portal-monitor__dots" aria-hidden="true">
                            <i></i><i></i><i></i>
                        </span>
                        <span class="portal-monitor__url">intra.tudrgroup.com</span>
                    </div>
                    <div class="portal-monitor__viewport portal-monitor__viewport--wide">
                        @include('partials.presentation-intra-index-screen', ['logo' => $logo])
                    </div>
                </div>
            </div>
            <span class="portal-monitor__neck"></span>
            <span class="portal-monitor__base"></span>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['monitor_caption'] ?? 'PC · Índice de portales' }}</figcaption>
    </figure>
</div>
