@php
    $tdgLogo = asset($slide['data']['tdg_logo'] ?? 'image/logoNewTDG.png');
    $tdgLogoDark = asset($slide['data']['tdg_logo_dark'] ?? 'image/logoTDG.png');
    $mailchimpLogo = asset($slide['data']['partner_logo'] ?? 'image/brands/mailchimp-logo.svg');
    $mailchimpMark = asset($slide['data']['partner_mark'] ?? 'image/brands/mailchimp-freddie.svg');
@endphp

<div class="mc-bridge" aria-hidden="false">
    <article class="mc-brand mc-brand--tdg liquid-glass">
        <img class="mc-brand__logo mc-brand__logo--tdg mc-brand__logo--light" src="{{ $tdgLogo }}" alt="Tu Doctor Group">
        <img class="mc-brand__logo mc-brand__logo--tdg mc-brand__logo--dark" src="{{ $tdgLogoDark }}" alt="Tu Doctor Group">
        <div class="mc-brand__copy">
            <div class="mc-brand__name">{{ $slide['data']['tdg_label'] ?? 'TDG Marketing' }}</div>
            <div class="mc-brand__hint">{{ $slide['data']['tdg_hint'] ?? '' }}</div>
        </div>
    </article>

    <div class="mc-connector" aria-hidden="true">
        <span class="mc-connector__mark">
            <img src="{{ $mailchimpMark }}" alt="">
        </span>
        <div class="mc-connector__line"></div>
        <div class="mc-connector__labels">
            @foreach ($slide['data']['flow_labels'] ?? [] as $label)
                <span>{{ $label }}</span>
            @endforeach
        </div>
    </div>

    <article class="mc-brand mc-brand--mailchimp">
        <img class="mc-brand__logo mc-brand__logo--mailchimp" src="{{ $mailchimpLogo }}" alt="Mailchimp">
        <div class="mc-brand__copy">
            <div class="mc-brand__name">{{ $slide['data']['partner_label'] ?? 'Mailchimp' }}</div>
            <div class="mc-brand__hint">{{ $slide['data']['partner_hint'] ?? '' }}</div>
        </div>
    </article>
</div>
