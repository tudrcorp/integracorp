<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Integracorp\IntegracorpHubAccessibleModules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.integracorp-hub')] #[Title('IntegraCorp - Sistema Integral de Gestión para Empresas')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user instanceof User && IntegracorpHubAccessibleModules::userHasAnyModule($user)) {
            $this->redirect(route('integracorp.hub.modules'), navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Indica tu correo electrónico.',
            'email.email' => 'El correo no tiene un formato válido.',
            'password.required' => 'Indica tu contraseña.',
        ]);

        $throttleKey = 'integracorp-hub-login:'.strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            throw ValidationException::withMessages([
                'email' => ['Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            ]);
        }

        $user = User::query()->where('email', $this->email)->first();

        if ($user === null || ! Hash::check($this->password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['Correo o contraseña incorrectos.'],
            ]);
        }

        if (($user->status ?? '') !== 'ACTIVO' && ($user->status ?? '') != 'ACTIVO') {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['Tu usuario no está activo. Contacta al administrador.'],
            ]);
        }

        if (! IntegracorpHubAccessibleModules::userHasAnyModule($user)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => ['No tienes módulos asignados en IntegraCorp. Solicita acceso al administrador.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, remember: $this->remember);
        request()->session()->regenerate();

        $modules = IntegracorpHubAccessibleModules::forUser($user);
        if (count($modules) === 1) {
            $this->redirect($modules[0]['url'], navigate: false);

            return;
        }

        $this->redirect(route('integracorp.hub.modules'), navigate: true);
    }
}; ?>

<x-integracorp.hub-auth-shell>
    <div class="ic-auth-form-wrap">
        @include('integracorp.partials.hub-auth-form-logo')

        @if (session('hub_login_status'))
            <p class="ic-auth-status" role="status">{{ session('hub_login_status') }}</p>
        @endif

        <form wire:submit="login" class="ic-auth-form">
            <header class="ic-auth-form__head">
                <h1 class="ic-auth-form__title">Bienvenido</h1>
                <p class="ic-auth-form__lead">Ingresa a tu cuenta de Integracorp.</p>
            </header>

            <div class="ic-auth-field">
                <label for="hub-email" class="ic-auth-field__label">Correo electrónico</label>
                <input
                    id="hub-email"
                    type="email"
                    autocomplete="username"
                    class="ic-auth-input"
                    wire:model="email"
                    placeholder="nombre@tudrencasa.com"
                    required
                />
                @error('email')
                    <p class="ic-auth-field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ic-auth-field">
                @include('integracorp.partials.hub-password-field', [
                    'id' => 'hub-password',
                    'model' => 'password',
                ])
                @error('password')
                    <p class="ic-auth-field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ic-auth-options">
                <label class="ic-auth-remember">
                    <input type="checkbox" class="ic-auth-remember__native" wire:model="remember" />
                    <span class="ic-auth-remember__box" aria-hidden="true">
                        <span class="ic-auth-remember__mark"></span>
                    </span>
                    Recordarme
                </label>
                <a href="{{ route('integracorp.hub.password.request') }}" class="ic-auth-link" wire:navigate>
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            @include('integracorp.partials.hub-submit-button', [
                'target' => 'login',
                'idle' => 'Entrar',
                'loading' => 'Verificando…',
            ])
        </form>
    </div>
</x-integracorp.hub-auth-shell>
