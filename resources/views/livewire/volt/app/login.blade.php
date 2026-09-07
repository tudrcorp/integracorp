<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAccount;
use App\Support\Storefront\StorefrontAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Entrar')] class extends Component
{
    public string $identifier = '';

    public string $password = '';

    public function mount(): void
    {
        if (StorefrontAuth::check()) {
            $this->redirect(
                StorefrontAuth::mustCompleteProfile()
                    ? route('storefront.profile')
                    : route('storefront.home'),
                navigate: true,
            );
        }
    }

    public function login(): void
    {
        $this->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Indica tu correo, teléfono o cédula.',
            'password.required' => 'Indica tu contraseña.',
        ]);

        $throttleKey = 'storefront-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            throw ValidationException::withMessages([
                'identifier' => ['Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            ]);
        }

        $user = StorefrontAccount::findByLoginIdentifier($this->identifier);

        if ($user === null || ! Hash::check($this->password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'identifier' => ['No reconocemos esos datos. Revisa tu correo, teléfono o cédula y tu clave.'],
            ]);
        }

        try {
            StorefrontAccount::assertCanAccess($user);
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, 60);

            throw $exception;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, true);
        request()->session()->regenerate();

        $this->redirect(
            StorefrontAuth::mustCompleteProfile($user)
                ? route('storefront.profile')
                : route('storefront.home'),
            navigate: true,
        );
    }
}; ?>

<div>
    <section class="sf-hero">
        <p class="sf-kicker">Tu cuenta</p>
        <h1 class="sf-title">Entra a la app</h1>
        <p class="sf-lead">Usa tu correo, teléfono o cédula con tu clave de IntegraCorp.</p>
    </section>

    @if (session('storefront_notice'))
        <p class="sf-welcome__notice" role="status">{{ session('storefront_notice') }}</p>
    @endif

    <section class="sf-section sf-glass">
        <div class="sf-field">
            <label for="sf-login-id">Correo, teléfono o cédula</label>
            <input
                id="sf-login-id"
                type="text"
                wire:model="identifier"
                autocomplete="username"
                autocapitalize="none"
                enterkeyhint="next"
                placeholder="Ej. 04121234567 o V12345678"
            >
            @error('identifier') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-login-password">Contraseña</label>
            <input
                id="sf-login-password"
                type="password"
                wire:model="password"
                autocomplete="current-password"
                enterkeyhint="go"
                wire:keydown.enter="login"
            >
            @error('password') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="sf-sticky-cta" style="display: grid; gap: 0.55rem;">
        <button type="button" class="sf-btn" wire:click="login" wire:loading.attr="disabled" wire:target="login" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'login', 'label' => 'Entrar', 'wait' => 'Verificando…'])
        </button>
        <a href="{{ route('storefront.register') }}" wire:navigate class="sf-btn sf-btn-ghost">Crear cuenta</a>
    </div>

    <p class="sf-or">¿Eres nuevo?</p>
    @include('storefront.partials.google-login-button', ['class' => 'sf-welcome__btn sf-welcome__btn--google'])
</div>
