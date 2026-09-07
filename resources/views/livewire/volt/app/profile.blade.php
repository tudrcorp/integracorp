<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAccount;
use App\Support\Storefront\StorefrontAuth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Mi perfil')] class extends Component
{
    public string $name = '';

    public string $nro_identification = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $required = false;

    public function mount(): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        $this->name = (string) $user->name;
        $this->email = (string) ($user->email ?? '');
        $this->phone = (string) ($user->phone ?? '');
        $this->nro_identification = (string) ($user->nro_identification ?: $user->identity_card ?: '');
        $this->required = StorefrontAuth::mustCompleteProfile($user);
    }

    public function save(): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'nro_identification' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:4', 'confirmed'],
        ], [
            'name.required' => 'Indica tu nombre.',
            'nro_identification.required' => 'Indica tu cédula.',
            'email.email' => 'El correo no es válido.',
            'password.min' => 'La contraseña debe tener al menos 4 caracteres (números o letras).',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        try {
            $updated = StorefrontAccount::updateProfile($user, [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'nro_identification' => $this->nro_identification,
                'password' => $this->password !== '' ? $this->password : null,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        session()->forget(StorefrontAccount::SESSION_FORCE_PROFILE);
        $this->password = '';
        $this->password_confirmation = '';
        $this->required = false;
        $this->name = (string) $updated->name;
        $this->email = (string) ($updated->email ?? '');
        $this->phone = (string) ($updated->phone ?? '');
        $this->nro_identification = (string) ($updated->nro_identification ?: '');

        session()->flash('storefront_notice', 'Perfil actualizado.');

        $this->redirect(route('storefront.home'), navigate: true);
    }
}; ?>

<div>
    <section class="sf-hero">
        <p class="sf-kicker">Cuenta</p>
        <h1 class="sf-title">{{ $required ? 'Completa tu perfil' : 'Mi perfil' }}</h1>
        <p class="sf-lead">
            {{ $required
                ? 'Necesitamos tu cédula y un teléfono o correo para seguir.'
                : 'Actualiza tus datos. Se guardan en tu usuario de IntegraCorp.' }}
        </p>
    </section>

    @if (session('storefront_notice'))
        <p class="sf-welcome__notice" role="status">{{ session('storefront_notice') }}</p>
    @endif

    <section class="sf-section sf-glass">
        <div class="sf-field">
            <label for="sf-profile-name">Nombre</label>
            <input id="sf-profile-name" type="text" wire:model="name" autocomplete="name">
            @error('name') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-profile-id">Cédula</label>
            <input id="sf-profile-id" type="text" wire:model="nro_identification" autocomplete="off" placeholder="V12345678">
            @error('nro_identification') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-profile-email">Correo</label>
            <input id="sf-profile-email" type="email" wire:model="email" autocomplete="email" autocapitalize="none">
            @error('email') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-profile-phone">Teléfono</label>
            <input id="sf-profile-phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel">
            @error('phone') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-profile-password">Nueva contraseña <span class="sf-field__hint">(opcional)</span></label>
            <input id="sf-profile-password" type="password" wire:model="password" autocomplete="new-password">
            @error('password') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label for="sf-profile-password2">Confirmar contraseña</label>
            <input id="sf-profile-password2" type="password" wire:model="password_confirmation" autocomplete="new-password" wire:keydown.enter="save">
        </div>
    </section>

    <div class="sf-sticky-cta" style="display: grid; gap: 0.55rem;">
        <button type="button" class="sf-btn" wire:click="save" wire:loading.attr="disabled" wire:target="save" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'save', 'label' => 'Guardar', 'wait' => 'Guardando…'])
        </button>
        @unless ($required)
            <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn sf-btn-ghost">Volver a planes</a>
        @endunless
    </div>
</div>
