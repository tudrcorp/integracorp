@php
    $variant = $variant ?? 'phone';
    $loginPhoto = $loginPhoto ?? asset('image/storefront/portal-paciente-login.jpg');
    $loginLogo = $loginLogo ?? asset('image/logoNewTDG.png');
@endphp

<div class="portal-login portal-login--{{ $variant }}">
    <img
        src="{{ $loginPhoto }}"
        alt=""
        class="portal-login__photo"
        width="1024"
        height="1024"
        decoding="async"
    >
    <span class="portal-login__veil" aria-hidden="true"></span>
    <div class="portal-login__stage">
        <div class="portal-login__card">
            <img src="{{ $loginLogo }}" alt="Tu Dr En Casa" class="portal-login__logo" width="152" height="48">
            <h3 class="portal-login__title">Portal del paciente</h3>
            <p class="portal-login__lead">Ingresa tu cédula y tu clave del portal para continuar.</p>
            <label class="portal-login__label">Documento de Identificación</label>
            <div class="portal-login__input">Ej.: 00112345678</div>
            <div class="portal-login__label-row">
                <span class="portal-login__label">Clave del portal</span>
                <span class="portal-login__link">¿Olvidaste tu clave?</span>
            </div>
            <div class="portal-login__input portal-login__input--secret">••••••••</div>
            <div class="portal-login__btn">Entrar al portal</div>
            <p class="portal-login__contact">¡Comunícate con nosotros!</p>
        </div>
    </div>
</div>
