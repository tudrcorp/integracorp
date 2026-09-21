<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontPlanNarrative;
use App\Support\Storefront\StorefrontQuoteCoverages;
use App\Support\Storefront\StorefrontQuoteReceipt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Coberturas')] class extends Component
{
    public string $code = '';

    /**
     * @var array<string, mixed>
     */
    public array $quote = [];

    /**
     * @var list<array{id: int, key: string, label: string, hint: string, annual: float, annual_label: string, persons: int, persons_label: string}>
     */
    public array $options = [];

    /**
     * @var list<int>
     */
    public array $selected = [];

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
        $options = StorefrontQuoteCoverages::options($record->detailsQuote, $planModel);

        if ($options === []) {
            $this->redirect(route('storefront.quote.frequency', ['code' => $record->code]), navigate: true);

            return;
        }

        $this->code = (string) $record->code;
        $this->options = $options;
        $this->quote = StorefrontQuoteReceipt::summary($record);
        $available = array_column($options, 'id');
        $restored = StorefrontQuoteCoverages::sanitize(
            StorefrontQuoteCoverages::resolve($record, $planModel),
            $available,
        );

        $this->selected = $restored !== []
            ? $restored
            : (count($available) === 1 ? $available : []);
    }

    public function toggle(int $id): void
    {
        $available = array_column($this->options, 'id');

        if (! in_array($id, $available, true)) {
            return;
        }

        $current = StorefrontQuoteCoverages::sanitize($this->selected, $available);

        if (in_array($id, $current, true)) {
            $current = array_values(array_filter($current, static fn (int $value): bool => $value !== $id));
        } else {
            $current[] = $id;
            sort($current);
        }

        $this->selected = $current;
        StorefrontQuoteCoverages::remember($this->code, $this->selected);
        $this->resetErrorBag('selected');
    }

    public function continue(): void
    {
        $available = array_column($this->options, 'id');
        $selected = StorefrontQuoteCoverages::sanitize($this->selected, $available);

        if ($selected === []) {
            $this->addError('selected', 'Elige al menos una cobertura para continuar.');

            return;
        }

        StorefrontQuoteCoverages::remember($this->code, $selected);

        $this->redirect(
            StorefrontQuoteCoverages::route('storefront.quote.frequency', ['code' => $this->code], $selected),
            navigate: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        $selected = StorefrontQuoteCoverages::sanitize($this->selected, array_column($this->options, 'id'));
        $annual = 0.0;

        foreach ($this->options as $option) {
            if (in_array($option['id'], $selected, true)) {
                $annual += (float) $option['annual'];
            }
        }

        $count = count($selected);
        $hasAmount = $annual > 0 && $count > 0;
        $amountLabel = $hasAmount
            ? StorefrontPlanNarrative::formatMoney(round($annual, 2))
            : '—';

        return [
            'count' => $count,
            'has_amount' => $hasAmount,
            'amount' => round($annual, 2),
            'amount_label' => $amountLabel,
            'count_label' => $count === 0
                ? 'Ninguna cobertura'
                : ($count === 1 ? '1 cobertura' : $count.' coberturas'),
        ];
    }

    public function isSelected(int $id): bool
    {
        return in_array($id, StorefrontQuoteCoverages::sanitize($this->selected), true);
    }
}; ?>

@php
    $totals = $this->totals();
@endphp

<div class="sf-pay sf-cov">
    <div class="sf-cov__scroll">
        <section class="sf-hero sf-hero--compact">
            <p class="sf-kicker">{{ $quote['code'] ?? '' }}</p>
            <h1 class="sf-title">¿Qué coberturas quieres afiliar?</h1>
            <p class="sf-lead">Marca una o varias. El total se actualiza al instante; después eliges cada cuánto pagar.</p>
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
        </article>

        <div class="sf-cov__list" role="group" aria-label="Coberturas">
            @foreach ($options as $option)
                <button
                    type="button"
                    wire:key="sf-cov-{{ $option['id'] }}"
                    wire:click="toggle({{ $option['id'] }})"
                    class="sf-cov__choice{{ $this->isSelected($option['id']) ? ' is-on' : '' }}"
                    aria-pressed="{{ $this->isSelected($option['id']) ? 'true' : 'false' }}"
                >
                    <span class="sf-cov__check" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none">
                            <path d="M5 10.5 8.5 14 15 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="sf-cov__copy">
                        <strong>{{ $option['label'] }}</strong>
                        <span>{{ $option['hint'] }}</span>
                    </span>
                    <span class="sf-cov__price">
                        <strong>{{ $option['annual_label'] }}</strong>
                        <span>al año</span>
                    </span>
                </button>
            @endforeach
        </div>

        @error('selected')
            <p class="sf-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="sf-cov__dock">
        <div class="sf-cov__total" role="status" aria-live="polite">
            <p class="sf-cov__total-kicker">{{ $totals['count_label'] }}</p>
            <p class="sf-cov__total-amount">{{ $totals['amount_label'] }}</p>
            <p class="sf-cov__total-hint">{{ $totals['has_amount'] ? 'Total anual de tu selección' : 'Elige al menos una cobertura' }}</p>
        </div>
        <button
            type="button"
            class="sf-btn{{ $totals['count'] === 0 ? ' is-disabled' : '' }}"
            wire:click="continue"
            wire:loading.attr="disabled"
            @disabled($totals['count'] === 0)
        >
            Continuar
        </button>
    </div>
</div>
