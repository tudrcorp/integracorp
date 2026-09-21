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

new #[Layout('components.layouts.storefront')] #[Title('Pagar cotización')] class extends Component
{
    public string $code = '';

    public string $frequency = StorefrontQuoteFrequency::Annual;

    /**
     * @var array<string, mixed>
     */
    public array $quote = [];

    public function mount(string $code, string $frequency): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        if (! StorefrontQuoteFrequency::isKnown($frequency)) {
            $this->redirect(StorefrontQuoteCoverages::route('storefront.quote.frequency', ['code' => $code], StorefrontQuoteCoverages::fromRequest()), navigate: true);

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
        $this->frequency = StorefrontQuoteFrequency::normalize($frequency);
        $this->quote = StorefrontQuoteReceipt::summary($record, $this->frequency, $selected);
    }
}; ?>

<div class="sf-pay">
    <section class="sf-hero sf-hero--compact">
        <p class="sf-kicker">{{ $quote['code'] ?? '' }}</p>
        <h1 class="sf-title">Tu pago {{ mb_strtolower((string) ($quote['frequency_label'] ?? 'anual')) }}</h1>
        <p class="sf-lead">Este es el monto de cada pago según la frecuencia que elegiste.</p>
    </section>

    <article class="sf-pay__calc sf-glass">
        <p class="sf-pay__calc-kicker">{{ $quote['frequency_label'] ?? 'Anual' }}</p>
        <p class="sf-pay__calc-amount">
            @if (($quote['amount_prefix'] ?? '') !== '')
                <span class="sf-pay__calc-prefix">{{ $quote['amount_prefix'] }}</span>
            @endif
            <span>{{ $quote['amount_label'] ?? 'Monto por confirmar' }}</span>
        </p>
        @if (($quote['amount_period'] ?? '') !== '')
            <p class="sf-pay__calc-period">{{ $quote['amount_period'] }}</p>
        @endif
        <p class="sf-pay__calc-hint">{{ $quote['breakdown'] ?? '' }}</p>
    </article>

    <article class="sf-pay__summary sf-glass">
        <p class="sf-quote-card__code">{{ $quote['code'] ?? '' }}</p>
        <h2 class="sf-quote-card__client">{{ $quote['client'] ?? '' }}</h2>
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
        @if (! empty($quote['has_receipt']))
            <p class="sf-pay__receipt-note">Ya hay un comprobante cargado. Puedes enviar otro si lo necesitas.</p>
        @endif
    </article>

    <div class="sf-pay__choices">
        <button type="button" class="sf-pay__choice is-soon" disabled aria-disabled="true">
            <span class="sf-pay__choice-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3.5" y="6" width="17" height="12" rx="2.2" />
                    <path d="M3.5 10h17" />
                </svg>
            </span>
            <span class="sf-pay__choice-copy">
                <strong>Pagar</strong>
                <span>Próximamente</span>
            </span>
        </button>

        <a href="{{ $quote['receipt_url'] ?? route('storefront.quote.receipt', ['code' => $code, 'frequency' => $frequency]) }}" wire:navigate class="sf-pay__choice">
            <span class="sf-pay__choice-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 16.5V7.75m0 0-3.2 3.2M12 7.75l3.2 3.2" />
                    <path d="M5 16.5v1.75A2.25 2.25 0 0 0 7.25 20.5h9.5A2.25 2.25 0 0 0 19 18.25V16.5" />
                </svg>
            </span>
            <span class="sf-pay__choice-copy">
                <strong>Cargar comprobante de pago</strong>
                <span>Foto o archivo desde tu teléfono</span>
            </span>
        </a>
    </div>
</div>
