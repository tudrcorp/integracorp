<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.integracorp-hub')] #[Title('IntegraCorp - Sistema Integral de Gestión para Empresas')] class extends Component
{
    public string $email = '';

    public ?string $statusMessage = null;

    public function sendResetLink(): void
    {
        $this->statusMessage = null;

        $this->validate([
            'email' => ['required', 'email', 'max:190'],
        ], [
            'email.required' => 'Indica tu correo electrónico.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        $throttleKey = 'integracorp-hub-reset:'.strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        Password::sendResetLink(['email' => $this->email]);

        $this->statusMessage = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña en los próximos minutos.';
        $this->reset('email');
    }
}; ?>

<x-integracorp.hub-auth-shell>
    <div class="ic-auth-form-wrap">
        @include('integracorp.partials.hub-auth-form-logo')

        <form wire:submit="sendResetLink" class="ic-auth-form">
            <a href="{{ route('home') }}" class="ic-auth-back" wire:navigate>
                <span class="ic-auth-back__line" aria-hidden="true"></span>
                Volver
            </a>

            <header class="ic-auth-form__head">
                <h1 class="ic-auth-form__title">¿Olvidaste tu <em>contraseña?</em></h1>
                <p class="ic-auth-form__lead">
                    @if ($statusMessage)
                        {{ $statusMessage }}
                    @else
                        Escribe tu correo y te enviaremos un enlace para restablecerla.
                    @endif
                </p>
            </header>

            @if (! $statusMessage)
                <div class="ic-auth-field">
                    <label for="hub-forgot-email" class="ic-auth-field__label">Correo electrónico</label>
                    <input
                        id="hub-forgot-email"
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

                @include('integracorp.partials.hub-submit-button', [
                    'target' => 'sendResetLink',
                    'idle' => 'Enviar enlace',
                    'loading' => 'Enviando…',
                ])
            @endif
        </form>
    </div>
</x-integracorp.hub-auth-shell>
