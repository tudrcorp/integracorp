<?php

declare(strict_types=1);

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.integracorp-hub')] #[Title('IntegraCorp - Sistema Integral de Gestión para Empresas')] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.required' => 'Indica tu correo electrónico.',
            'password.required' => 'Indica tu nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user): void {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('hub_login_status', 'Tu contraseña fue actualizada. Ya puedes entrar con tu nueva clave.');

        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<x-integracorp.hub-auth-shell>
    <div class="ic-auth-form-wrap">
        @include('integracorp.partials.hub-auth-form-logo')

        <form wire:submit="resetPassword" class="ic-auth-form">
            <a href="{{ route('home') }}" class="ic-auth-back" wire:navigate>
                <span class="ic-auth-back__line" aria-hidden="true"></span>
                Volver
            </a>

            <header class="ic-auth-form__head">
                <h1 class="ic-auth-form__title">Restablecer <em>contraseña</em></h1>
                <p class="ic-auth-form__lead">Elige una contraseña nueva para tu cuenta de Integracorp.</p>
            </header>

            <div class="ic-auth-field">
                <label for="hub-reset-email" class="ic-auth-field__label">Correo electrónico</label>
                <input
                    id="hub-reset-email"
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
                    'id' => 'hub-reset-password',
                    'model' => 'password',
                    'label' => 'Nueva contraseña',
                    'autocomplete' => 'new-password',
                ])
                @error('password')
                    <p class="ic-auth-field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ic-auth-field">
                @include('integracorp.partials.hub-password-field', [
                    'id' => 'hub-reset-password-confirm',
                    'model' => 'password_confirmation',
                    'label' => 'Confirmar contraseña',
                    'autocomplete' => 'new-password',
                ])
            </div>

            @include('integracorp.partials.hub-submit-button', [
                'target' => 'resetPassword',
                'idle' => 'Guardar contraseña',
                'loading' => 'Guardando…',
            ])
        </form>
    </div>
</x-integracorp.hub-auth-shell>
