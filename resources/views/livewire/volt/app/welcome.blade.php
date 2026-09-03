<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAuth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront-welcome')] #[Title('Bienvenida')] class extends Component
{
    public function mount(): void
    {
        if (StorefrontAuth::currentIsAgent()) {
            $this->redirect(route('storefront.home'), navigate: true);
        }
    }
}; ?>

<div class="sf-welcome">
    <img
        class="sf-welcome__photo"
        src="{{ asset('image/storefront/welcome.jpg') }}"
        alt=""
        width="1024"
        height="1536"
        decoding="async"
        fetchpriority="high"
    >
    <span class="sf-welcome__shade" aria-hidden="true"></span>

    <header class="sf-welcome__brand">
        <img src="{{ asset('image/logoNewPdf.png') }}" alt="Tu Dr En Casa" width="168" height="43">
    </header>

    <section class="sf-welcome__hero">
        <p class="sf-welcome__kicker">Asistencia médica</p>
        <h1 class="sf-welcome__title">Tu propia<br><em>asistencia médica</em></h1>
    </section>

    <div class="sf-welcome__dock">
        @if (session('storefront_notice'))
            <p class="sf-welcome__notice" role="status">{{ session('storefront_notice') }}</p>
        @endif

        <a href="{{ route('storefront.home') }}" wire:navigate class="sf-welcome__btn sf-welcome__btn--plans">
            Ver planes
        </a>

        <p class="sf-welcome__agent">
            <a href="{{ route('storefront.login') }}" wire:navigate>¿Eres agente? Entra aquí</a>
        </p>

        <p class="sf-welcome__legal">Al continuar aceptas cotizar y gestionar tu asistencia con Tu Dr En Casa.</p>
    </div>
</div>
