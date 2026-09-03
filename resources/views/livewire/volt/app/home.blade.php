<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontPlanNarrative;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Planes')] class extends Component
{
    public bool $asAgent = false;

    public string $greeting = '';

    public function mount(): void
    {
        $this->asAgent = StorefrontAuth::currentIsAgent();
        $this->greeting = $this->asAgent
            ? 'Listo para cotizar, '.StorefrontAuth::displayName()
            : 'Tu plan de asistencia, al alcance del pulgar';
    }

    public function cards()
    {
        return StorefrontCatalog::cards();
    }
}; ?>

<div>
    <section class="sf-hero">
        @if ($asAgent)
            <span class="sf-agent-chip">Sesión de agente</span>
        @endif
        <p class="sf-kicker">Catálogo</p>
        <h1 class="sf-title">{{ $greeting }}</h1>
        <p class="sf-lead">
            @if ($asAgent)
                Elige un plan, indica el grupo familiar del cliente y genera la cotización con tu código.
            @else
                Tres planes claros. Beneficios visibles. Tarifas por edad. Cotiza en menos de un minuto, sin crear cuenta.
            @endif
        </p>
    </section>

    @forelse ($this->cards() as $card)
        @php
            $plan = $card['plan'];
            $narrative = $card['narrative'];
        @endphp
        <a
            href="{{ route('storefront.plan', $plan) }}"
            wire:navigate
            class="sf-plan-card"
            wire:key="plan-card-{{ $plan->id }}"
        >
            <span class="sf-plan-card__media">
                <img
                    class="sf-plan-card__photo"
                    src="{{ asset($narrative['cover']) }}"
                    alt=""
                    width="1100"
                    height="733"
                    decoding="async"
                    @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                >
                <span class="sf-plan-card__shade" aria-hidden="true"></span>
            </span>
            <span class="sf-plan-card__body">
                @if (($narrative['kicker'] ?? '') !== '')
                    <span class="sf-plan-card__kicker">{{ $narrative['kicker'] }}</span>
                @endif
                <h2 class="sf-plan-card__title">{{ $narrative['title'] }}</h2>
                <p class="sf-plan-card__promise">{{ $narrative['promise'] }}</p>
                <div class="sf-plan-card__meta">
                    <div class="sf-price">
                        @if ($card['desde'] !== null)
                            Desde {{ StorefrontPlanNarrative::formatMoney($card['desde']) }}
                        @else
                            Ver tarifas
                        @endif
                        <small>{{ $card['people_label'] }}</small>
                    </div>
                    <span class="sf-cta-pill">Ver plan →</span>
                </div>
            </span>
        </a>
    @empty
        <div class="sf-empty sf-glass sf-section">
            No hay planes publicados en este momento.
        </div>
    @endforelse
</div>
