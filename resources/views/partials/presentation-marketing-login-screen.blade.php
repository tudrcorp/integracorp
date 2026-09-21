@php
    $variant = $variant ?? 'phone';
    $loginLogo = $loginLogo ?? asset('image/logoNewTDG.png');
@endphp

<div class="mkt-login mkt-login--{{ $variant }}">
    <span class="mkt-login__orb mkt-login__orb--a" aria-hidden="true"></span>
    <span class="mkt-login__orb mkt-login__orb--b" aria-hidden="true"></span>
    <div class="mkt-login__stage">
        <div class="mkt-login__card">
            <img src="{{ $loginLogo }}" alt="Tu Dr Group" class="mkt-login__logo" width="152" height="48">
            <h3 class="mkt-login__title">Iniciar sesión</h3>
            <p class="mkt-login__pill">Aplicación de Marketing</p>
            <span class="mkt-login__rule" aria-hidden="true"></span>
            <label class="mkt-login__label">Correo electrónico</label>
            <div class="mkt-login__field">
                <span class="mkt-login__icon" aria-hidden="true"></span>
                <span>correo@ejemplo.com</span>
            </div>
            <label class="mkt-login__label">Contraseña</label>
            <div class="mkt-login__field">
                <span class="mkt-login__icon mkt-login__icon--lock" aria-hidden="true"></span>
                <span class="mkt-login__secret">••••••••</span>
            </div>
            <p class="mkt-login__remember">Recordarme</p>
            <div class="mkt-login__btn">Iniciar sesión</div>
        </div>
    </div>
</div>
