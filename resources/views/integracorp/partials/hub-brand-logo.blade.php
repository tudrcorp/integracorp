@php
    $hubBrandPlacementClass = match ($placement ?? null) {
        'header-center' => ' ic-hub-brand-mark--header-center',
        'header-bar' => ' ic-hub-brand-mark--header-bar',
        'login-center' => ' ic-hub-brand-mark--login-center',
        default => '',
    };
    $hubBrandUsesDarkGoldLogo = ($darkLogo ?? null) === 'gold';
    $hubBrandGoldModifierClass = $hubBrandUsesDarkGoldLogo ? ' ic-hub-brand-mark--dark-logo-gold' : '';
    $hubBrandMarkClass = 'ic-hub-brand-mark'.$hubBrandPlacementClass.$hubBrandGoldModifierClass;
@endphp
<a
    href="{{ route('home') }}"
    class="{{ $hubBrandMarkClass }}"
    aria-label="Tu Dr Group — inicio"
    wire:navigate
>
    <img
        class="ic-hub-brand-mark__logo ic-hub-brand-mark__logo--on-dark"
        src="{{ asset('image/logoTDG.png') }}"
        alt=""
        width="160"
        height="48"
        decoding="async"
    />
    @if ($hubBrandUsesDarkGoldLogo)
        <img
            class="ic-hub-brand-mark__logo ic-hub-brand-mark__logo--on-dark-gold"
            src="{{ asset('image/hub-auth-logo-gold.png') }}"
            alt=""
            width="160"
            height="48"
            decoding="async"
        />
    @endif
    <img
        class="ic-hub-brand-mark__logo ic-hub-brand-mark__logo--on-light"
        src="{{ asset('image/logoNewTDG.png') }}"
        alt=""
        width="160"
        height="48"
        decoding="async"
    />
</a>
