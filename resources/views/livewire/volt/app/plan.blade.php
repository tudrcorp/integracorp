<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontPlanNarrative;
use App\Support\Storefront\StorefrontPlanView;
use App\Support\Storefront\StorefrontQuoteDraft;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Plan')] class extends Component
{
    public int $planId;

    public bool $asAgent = false;

    /** @var array<string, mixed> */
    public array $product = [];

    public function mount(int $plan): void
    {
        $model = StorefrontCatalog::findActiveBasic($plan);

        abort_unless($model !== null, 404);

        $this->planId = (int) $model->getKey();
        $this->asAgent = StorefrontAuth::currentIsAgent();
        $this->product = StorefrontPlanView::make($model);
    }

    public function startQuote()
    {
        StorefrontQuoteDraft::forPlan($this->planId);

        return $this->redirect(
            route('storefront.quote.people', $this->planId),
            navigate: true,
        );
    }
}; ?>

<div>
<div
    class="sf-product"
    x-data="{ benefitsOpen: false }"
    x-init="$watch('benefitsOpen', value => document.body.classList.toggle('is-benefits-open', !!value))"
    x-on:keydown.escape.window="benefitsOpen = false"
    x-on:livewire:navigating.window="benefitsOpen = false; document.body.classList.remove('is-benefits-open')"
>
    @php
        $narrative = $product['narrative'];
        $benefits = $product['benefits'];
        $benefitCount = count($benefits['rows'] ?? []);
        $rateCount = count($product['rates'] ?? []);
    @endphp

    <div class="sf-product__scene">
        <img
            class="sf-product__photo"
            src="{{ asset($narrative['cover']) }}"
            alt=""
            width="1100"
            height="733"
            decoding="async"
            fetchpriority="high"
        >
        <span class="sf-product__shade" aria-hidden="true"></span>

        <div class="sf-product__copy">
            @if ($asAgent)
                <span class="sf-agent-chip">Cotiza este plan a tu cliente</span>
            @endif
            <p class="sf-product__kicker">{{ $narrative['kicker'] }}</p>
            <h1 class="sf-product__title">{{ $narrative['title'] }}</h1>
            <p class="sf-product__hook">{{ $narrative['audience'] }}</p>
        </div>

        <div class="sf-product__dock">
            <button
                type="button"
                class="sf-btn sf-btn-ghost"
                x-on:click="benefitsOpen = true; $dispatch('storefront-close-menu')"
                x-bind:aria-expanded="benefitsOpen.toString()"
                aria-controls="sf-product-sheet"
            >
                Ver Beneficios
            </button>
            <button type="button" class="sf-btn" wire:click="startQuote" wire:loading.attr="disabled" wire:target="startQuote" wire:loading.class="is-busy">
                @include('storefront.partials.btn-loading', ['target' => 'startQuote', 'label' => 'Cotizar', 'wait' => 'Abriendo…'])
            </button>
        </div>
    </div>

    <div
        class="storefront-overlay sf-product-overlay"
        x-cloak
        x-bind:class="benefitsOpen && 'is-open'"
        x-bind:aria-hidden="(! benefitsOpen).toString()"
        x-bind:inert="! benefitsOpen"
        x-on:click.self="benefitsOpen = false"
    >
        <section
            id="sf-product-sheet"
            class="sf-product-sheet"
            x-on:click.stop
            role="dialog"
            aria-modal="true"
            aria-label="Beneficios del plan"
        >
            <div class="sf-product-sheet__grab">
                <span class="sf-product-sheet__handle" aria-hidden="true"></span>
            </div>

            <header class="sf-product-sheet__head">
                <span class="sf-product-sheet__badge">{{ $narrative['kicker'] }}</span>
                <h2>{{ $narrative['title'] }}</h2>
            </header>

            <div class="sf-product-sheet__stats" aria-label="Resumen del plan">
                <div>
                    <strong>
                        @if ($product['desde'] !== null)
                            {{ StorefrontPlanNarrative::formatMoney($product['desde']) }}
                        @else
                            —
                        @endif
                    </strong>
                    <span>Desde / año</span>
                </div>
                <div>
                    <strong>{{ $benefitCount }}</strong>
                    <span>Beneficios</span>
                </div>
                <div>
                    <strong>{{ $rateCount }}</strong>
                    <span>Rangos</span>
                </div>
            </div>

            <div class="sf-product-sheet__body">
                <h3>Qué incluye</h3>
                <div class="sf-product-sheet__includes">
                    @forelse ($benefits['rows'] as $row)
                        <div class="sf-product-sheet__row">
                            <span class="sf-product-sheet__icon" aria-hidden="true">✓</span>
                            <div>
                                <strong>{{ StorefrontPlanNarrative::sentenceLabel((string) ($row['benefit_label'] ?? 'Beneficio')) }}</strong>
                            </div>
                        </div>
                    @empty
                        <p class="sf-product-sheet__lead">Este plan todavía está cargando su estructura de beneficios.</p>
                    @endforelse
                </div>

                <h3>Rangos de edad y tarifas</h3>
                <div class="sf-product-sheet__rates">
                    @forelse ($product['rates'] as $rate)
                        @php
                            $rateCells = array_values($rate['cells'] ?? []);
                        @endphp
                        @if (count($rateCells) <= 1)
                            <div class="sf-product-sheet__rate">
                                <span class="sf-product-sheet__rate-label">{{ StorefrontPlanNarrative::sentenceLabel((string) $rate['label']) }}</span>
                                <span class="sf-product-sheet__rate-values">
                                    @foreach ($rateCells as $cell)
                                        <span>{{ filled($cell['value'] ?? null) ? $cell['value'] : '—' }}</span>
                                    @endforeach
                                </span>
                            </div>
                        @else
                            <div class="sf-product-sheet__rate-group">
                                <p class="sf-product-sheet__rate-label">{{ StorefrontPlanNarrative::sentenceLabel((string) $rate['label']) }}</p>
                                @foreach ($rateCells as $cell)
                                    <div class="sf-product-sheet__rate">
                                        <span class="sf-product-sheet__rate-coverage">{{ StorefrontPlanNarrative::sentenceLabel((string) ($cell['label'] ?? 'Cobertura')) }}</span>
                                        <span>{{ filled($cell['value'] ?? null) ? $cell['value'] : '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @empty
                        <p class="sf-product-sheet__lead">Las tarifas de este plan se confirman al cotizar.</p>
                    @endforelse
                </div>
                @if (! $product['is_package'])
                    <p class="sf-product-sheet__note">El precio final depende de la cobertura que elijas al cotizar.</p>
                @endif
            </div>

            <div class="sf-product-sheet__pay">
                <div>
                    <span>Desde</span>
                    <strong>
                        @if ($product['desde'] !== null)
                            {{ StorefrontPlanNarrative::formatMoney($product['desde']) }}
                        @else
                            Al cotizar
                        @endif
                    </strong>
                </div>
                <button type="button" class="sf-product-sheet__cta" wire:click="startQuote" wire:loading.attr="disabled" wire:target="startQuote" wire:loading.class="is-busy">
                    @include('storefront.partials.btn-loading', ['target' => 'startQuote', 'label' => $asAgent ? 'Cotizar para un cliente' : 'Cotizar', 'wait' => 'Abriendo…'])
                </button>
            </div>
        </section>
    </div>
</div>
</div>
