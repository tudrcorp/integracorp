<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontQuoteDraft;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Tus datos')] class extends Component
{
    public int $planId;

    public bool $asAgent = false;

    public string $full_name = '';

    public string $email = '';

    public string $phone = '';

    public function mount(int $plan): void
    {
        $model = StorefrontCatalog::findActiveBasic($plan);
        abort_unless($model !== null, 404);

        $this->planId = (int) $model->getKey();
        $this->asAgent = StorefrontAuth::currentIsAgent();

        $draft = StorefrontQuoteDraft::forPlan($this->planId);

        if ($draft['people'] === [] && $draft['ranges'] === []) {
            $this->redirect(route('storefront.quote.people', $this->planId), navigate: true);

            return;
        }

        $this->full_name = (string) $draft['full_name'];
        $this->email = (string) $draft['email'];
        $this->phone = (string) $draft['phone'];
    }

    public function continue()
    {
        $this->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:20'],
        ], [
            'full_name.required' => 'Indica el nombre y apellido.',
            'email.required' => 'Indica un correo electrónico.',
            'email.email' => 'El correo no es válido.',
            'phone.required' => 'Indica un teléfono de contacto.',
            'phone.min' => 'El teléfono debe tener al menos 10 dígitos.',
        ]);

        StorefrontQuoteDraft::saveContact($this->planId, $this->full_name, $this->email, $this->phone);

        return $this->redirect(route('storefront.quote.confirm', $this->planId), navigate: true);
    }
}; ?>

<div class="sf-quote">
    @include('storefront.partials.quote-steps', ['step' => 2])

    <section class="sf-hero">
        <p class="sf-kicker">Un último dato</p>
        <h1 class="sf-title">{{ $asAgent ? 'Datos del cliente' : '¿A quién enviamos la cotización?' }}</h1>
        <p class="sf-lead">
            @if ($asAgent)
                Nombre, correo y teléfono de quien va a recibir la propuesta.
            @else
                Para enviarte la cotización y que un asesor pueda acompañarte. Sin contraseñas.
            @endif
        </p>
    </section>

    <section class="sf-section sf-glass">
        <div class="sf-field">
            <label>Nombre y apellido</label>
            <input type="text" wire:model="full_name" autocomplete="name" autocapitalize="words" placeholder="Como figura en la cédula">
            @error('full_name') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label>Correo</label>
            <input type="email" wire:model="email" autocomplete="email" autocapitalize="none" placeholder="tucorreo@email.com">
            @error('email') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
        <div class="sf-field">
            <label>Teléfono</label>
            <input type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="0414 000 0000">
            @error('phone') <p class="sf-error">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="sf-sticky-cta">
        <button type="button" class="sf-btn" wire:click="continue" wire:loading.attr="disabled" wire:target="continue" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'continue', 'label' => 'Revisar cotización', 'wait' => 'Guardando…'])
        </button>
        <a href="{{ route('storefront.quote.people', $planId) }}" wire:navigate class="sf-btn sf-btn-ghost">Volver</a>
    </div>
</div>
