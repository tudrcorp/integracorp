<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Quotes\InteractiveIndividualQuoteView;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontPlanNarrative;
use App\Support\Storefront\StorefrontQuoteCoverages;
use App\Support\Storefront\StorefrontQuotesIndex;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Cotización lista')] class extends Component
{
    public string $code = '';

    public string $planTitle = 'Plan';

    public string $displayName = '';

    public bool $asAgent = false;

    public string $personsLabel = '';

    public string $payUrl = '';

    /**
     * @var array{has_amount: bool, amount: float, amount_label: string, amount_prefix: string, amount_period: string}
     */
    public array $payment = [
        'has_amount' => false,
        'amount' => 0.0,
        'amount_label' => 'Monto por confirmar',
        'amount_prefix' => '',
        'amount_period' => '',
    ];

    public function mount(string $code): void
    {
        $record = IndividualQuote::query()
            ->with(['detailsQuote.ageRange', 'detailsQuote.coverage'])
            ->where('code', $code)
            ->first();

        abort_unless($record instanceof IndividualQuote, 404);

        $this->code = (string) $record->code;
        $this->displayName = StorefrontPlanNarrative::personName((string) $record->full_name);
        $this->asAgent = StorefrontAuth::currentIsAgent();

        $plan = Plan::query()->find((int) $record->plan);
        $this->planTitle = $plan instanceof Plan
            ? StorefrontPlanNarrative::for($plan)['title']
            : 'Plan';
        $this->payment = StorefrontQuotesIndex::paymentFromDetails(
            $record->detailsQuote,
            $plan instanceof Plan ? $plan : null,
        );
        $this->payUrl = StorefrontQuoteCoverages::needsSelection(
            $plan instanceof Plan ? $plan : null,
            $record->detailsQuote,
        )
            ? route('storefront.quote.coverages', ['code' => $record->code])
            : route('storefront.quote.frequency', ['code' => $record->code]);

        if ($plan instanceof Plan) {
            $view = InteractiveIndividualQuoteView::from($record, $plan, $record->detailsQuote);
            $this->personsLabel = (string) $view['persons_label'];
        }
    }
}; ?>

<div
    class="sf-quote sf-quote--result"
    x-data="{
        copied: false,
        async copyCode() {
            try {
                await navigator.clipboard.writeText(@js($code));
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            } catch (error) {
                this.copied = false;
            }
        },
    }"
>
    <section class="sf-hero sf-hero--compact sf-hero--result">
        <span class="sf-ticket__seal" aria-hidden="true">✓</span>
        <h1 class="sf-title">Tu cotización está lista</h1>
        <p class="sf-lead">{{ $asAgent ? 'Quedó registrada a tu nombre.' : 'Guarda el código para afiliarte.' }}</p>
    </section>

    <article class="sf-ticket sf-ticket--compact sf-glass">
        <div class="sf-ticket__code-row">
            <div>
                <p class="sf-ticket__kicker">Código de cotización</p>
                <p class="sf-ticket__code" id="sf-quote-code">{{ $code }}</p>
            </div>
            <button type="button" class="sf-ticket__copy sf-ticket__copy--mini" x-on:click="copyCode()">
                <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
            </button>
        </div>

        <h2 class="sf-quote-card__client">{{ $displayName !== '' ? $displayName : 'Sin nombre' }}</h2>
        @include('storefront.partials.quote-plan-row', [
            'quote' => $payment,
            'planLabel' => StorefrontPlanNarrative::planLabel($planTitle),
            'personsLabel' => $personsLabel,
            'showPersons' => $personsLabel !== '',
        ])
    </article>

    <div class="sf-sticky-cta">
        <a href="{{ $payUrl }}" wire:navigate class="sf-btn">Pagar o cargar comprobante</a>
        <a href="{{ route('storefront.quote.proposal', $code) }}" wire:navigate class="sf-btn sf-btn-ghost">Ver propuesta</a>
    </div>
</div>
