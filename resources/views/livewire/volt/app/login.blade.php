<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Entrar')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        if (StorefrontAuth::currentIsAgent()) {
            $this->redirect(route('storefront.home'), navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Indica tu correo.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Indica tu contraseña.',
        ]);

        $throttleKey = 'storefront-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            ]);
        }

        $ok = Auth::attempt([
            'email' => mb_strtolower(trim($this->email)),
            'password' => $this->password,
        ], true);

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['No reconocemos esa cuenta. Usa el correo y la clave de tu usuario de agente.'],
            ]);
        }

        $user = Auth::user();

        if (! $user instanceof User || ! StorefrontAuth::canLoginAsAgent($user)) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['Esta app es para agentes. El cliente no necesita iniciar sesión.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        request()->session()->regenerate();

        $this->redirect(route('storefront.home'), navigate: true);
    }
}; ?>

<div>
    <section class="sf-hero">
        <p class="sf-kicker">Agentes</p>
        <h1 class="sf-title">Entra con tu cuenta</h1>
        <p class="sf-lead">La misma de IntegraCorp. Aquí cotizas a tu nombre; el cliente, por ahora, no inicia sesión.</p>
    </section>

    @if (session('storefront_notice'))
        <p class="sf-welcome__notice" role="status">{{ session('storefront_notice') }}</p>
    @endif

    @include('storefront.partials.google-login-button')

    <p class="sf-or">o con tu correo de agente</p>

    <section class="sf-section sf-glass">
        <div class="sf-field">
            <label>Correo</label>
            <input type="email" wire:model="email" autocomplete="username" autocapitalize="none" placeholder="agente@tudrencasa.com">
            @error('email') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label>Contraseña</label>
            <input type="password" wire:model="password" autocomplete="current-password">
            @error('password') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="sf-sticky-cta" style="display: grid; gap: 0.55rem;">
        <button type="button" class="sf-btn" wire:click="login" wire:loading.attr="disabled" wire:target="login" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'login', 'label' => 'Entrar', 'wait' => 'Verificando…'])
        </button>
        <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn sf-btn-ghost">Ver planes</a>
    </div>
</div>
