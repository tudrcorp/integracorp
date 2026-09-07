<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAccount;
use App\Support\Storefront\StorefrontAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Crear cuenta')] class extends Component
{
    public string $name = '';

    public string $nro_identification = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (StorefrontAuth::check()) {
            $this->redirect(route('storefront.home'), navigate: true);
        }
    }

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'nro_identification' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ], [
            'name.required' => 'Indica tu nombre.',
            'nro_identification.required' => 'Indica tu cédula.',
            'password.required' => 'Crea una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 4 caracteres (números o letras).',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $throttleKey = 'storefront-register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'name' => ['Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            ]);
        }

        try {
            $user = StorefrontAccount::register([
                'name' => $this->name,
                'nro_identification' => $this->nro_identification,
                'password' => $this->password,
            ]);
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, 60);

            throw $exception;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, true);
        request()->session()->regenerate();

        $this->redirect(route('storefront.home'), navigate: true);
    }
}; ?>

<div>
    <section class="sf-hero">
        <p class="sf-kicker">Nuevo</p>
        <h1 class="sf-title">Crea tu cuenta</h1>
        <p class="sf-lead">Nombre, cédula y tu clave. Correo y teléfono los actualizas después en Mi perfil.</p>
    </section>

    @if (session('storefront_notice'))
        <p class="sf-welcome__notice" role="status">{{ session('storefront_notice') }}</p>
    @endif

    @include('storefront.partials.google-login-button', ['class' => 'sf-welcome__btn sf-welcome__btn--google'])
    <p class="sf-or">o completa el formulario</p>

    <section class="sf-section sf-glass">
        <div class="sf-field">
            <label for="sf-reg-name">Nombre</label>
            <input id="sf-reg-name" type="text" wire:model="name" autocomplete="name" placeholder="Tu nombre">
            @error('name') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-reg-id">Cédula</label>
            <input id="sf-reg-id" type="text" wire:model="nro_identification" autocomplete="off" inputmode="text" placeholder="V12345678">
            @error('nro_identification') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-reg-password">Contraseña</label>
            <input id="sf-reg-password" type="password" wire:model="password" autocomplete="new-password">
            @error('password') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-reg-password2">Confirmar contraseña</label>
            <input id="sf-reg-password2" type="password" wire:model="password_confirmation" autocomplete="new-password" wire:keydown.enter="register">
        </div>
    </section>

    <div class="sf-sticky-cta" style="display: grid; gap: 0.55rem;">
        <button type="button" class="sf-btn" wire:click="register" wire:loading.attr="disabled" wire:target="register" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'register', 'label' => 'Crear cuenta', 'wait' => 'Creando…'])
        </button>
        <a href="{{ route('storefront.login') }}" wire:navigate class="sf-btn sf-btn-ghost">Ya tengo cuenta</a>
    </div>
</div>
