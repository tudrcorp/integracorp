<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontQuoteCoverages;
use App\Support\Storefront\StorefrontQuoteFrequency;
use App\Support\Storefront\StorefrontQuoteReceipt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Frecuencia de pago')] class extends Component
{
    public string $code = '';

    /**
     * @var array<string, mixed>
     */
    public array $quote = [];

    /**
     * @var list<array{key: string, label: string, hint: string, url: string}>
     */
    public array $choices = [];

    public function mount(string $code): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        $record = StorefrontQuoteReceipt::findOwned($user, $code);
        $plan = Plan::query()->find((int) $record->plan);
        $planModel = $plan instanceof Plan ? $plan : null;
        $selected = StorefrontQuoteCoverages::guard($record, $planModel);

        if ($selected === null) {
            $this->redirect(route('storefront.quote.coverages', ['code' => $record->code]), navigate: true);

            return;
        }

        $this->code = (string) $record->code;
        $this->quote = StorefrontQuoteReceipt::summary($record, StorefrontQuoteFrequency::Annual, $selected);
        $this->choices = array_map(static fn (array $choice): array => [
            ...$choice,
            'url' => StorefrontQuoteCoverages::route('storefront.quote.pay', [
                'code' => $record->code,
                'frequency' => $choice['key'],
            ], $selected),
        ], StorefrontQuoteFrequency::choices());
    }
}; ?>

<div class="sf-pay sf-freq">
    <section class="sf-hero sf-hero--compact">
        <p class="sf-kicker">{{ $quote['code'] ?? '' }}</p>
        <h1 class="sf-title">¿Cada cuánto quieres pagar?</h1>
        <p class="sf-lead">Elige la frecuencia. En la siguiente pantalla verás el monto de cada pago.</p>
    </section>

    <article class="sf-pay__summary sf-glass">
        <p class="sf-quote-card__code">{{ $quote['code'] ?? '' }}</p>
        <h2 class="sf-quote-card__client">{{ $quote['client'] ?? 'Sin nombre' }}</h2>
        <p class="sf-quote-card__plan">
            {{ $quote['plan'] ?? 'Plan' }}
            @if (($quote['persons'] ?? 0) > 0)
                <span class="sf-quote-card__sep" aria-hidden="true">·</span>
                {{ $quote['persons_label'] ?? '' }}
            @endif
        </p>
        @if (($quote['coverages_label'] ?? '') !== '')
            <p class="sf-pay__coverages">{{ $quote['coverages_label'] }}</p>
        @endif
    </article>

    <div class="sf-pay__choices" role="list">
        @foreach ($choices as $choice)
            <a
                href="{{ $choice['url'] }}"
                wire:navigate
                wire:key="sf-freq-{{ $choice['key'] }}"
                class="sf-pay__choice"
                role="listitem"
            >
                <span class="sf-pay__choice-copy">
                    <strong>{{ $choice['label'] }}</strong>
                    <span>{{ $choice['hint'] }}</span>
                </span>
                <span class="sf-freq__chevron" aria-hidden="true">›</span>
            </a>
        @endforeach
    </div>
</div>
