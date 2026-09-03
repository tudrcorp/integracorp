<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Quotes\InteractiveIndividualQuoteView;
use Flux\Flux;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.interactive')] #[Title('Tu cotización')] class extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $view = [];

    public function mount(): void
    {
        try {
            $quoteId = (int) Crypt::decryptString((string) Route::current()->parameter('quote'));
        } catch (\Throwable) {
            abort(404);
        }

        $record = IndividualQuote::query()
            ->with(['detailsQuote.ageRange', 'detailsQuote.coverage'])
            ->find($quoteId);

        abort_unless($record instanceof IndividualQuote, 404);

        $plan = Plan::query()->find((int) $record->plan);

        abort_unless($plan instanceof Plan, 404);

        $this->view = InteractiveIndividualQuoteView::from($record, $plan, $record->detailsQuote);
    }

    public function download()
    {
        $code = (string) ($this->view['code'] ?? '');
        $file = public_path('storage/quotes/'.$code.'.pdf');

        if ($code === '' || ! is_file($file)) {
            Flux::toast(
                heading: 'PDF no disponible',
                text: 'La cotización aún no tiene el documento listo. Un asesor puede reenviártelo.',
                variant: 'danger',
            );

            return;
        }

        return response()->download($file, $code.'.pdf');
    }
}; ?>

<div
    class="iq-quote"
    x-data="{ selected: @js($view['default_coverage_key']) }"
>
    <header class="iq-quote__top">
        <img class="iq-quote__logo" src="{{ asset('image/logoNewTDG.png') }}" alt="Tu Dr En Casa">
        <p class="iq-quote__kicker">Propuesta económica</p>
        <h1 class="iq-quote__title">{{ $view['plan_title'] }}</h1>
        <p class="iq-quote__meta">
            {{ $view['client_name'] }}
            @if ($view['date_label'] !== '')
                · {{ $view['date_label'] }}
            @endif
        </p>
        <p class="iq-quote__code">{{ $view['code'] }} · {{ $view['agent_label'] }}</p>
    </header>

    <section class="iq-hero" aria-label="Total cotizado">
        <p class="iq-hero__label">{{ $view['mode'] === 'coverages' ? 'Costo según cobertura' : 'Total del grupo' }}</p>
        <p class="iq-hero__amount">{{ $view['headline'] }}</p>
        <p class="iq-hero__hint">{{ $view['persons_label'] }}. {{ $view['headline_hint'] }}</p>
    </section>

    @if ($view['mode'] === 'coverages' && $view['coverages'] !== [])
        <section class="iq-card" aria-label="Coberturas">
            <h2>Coberturas</h2>
            <p class="iq-card__lead">Toca una cobertura para ver cómo se paga. Abajo está el detalle por edad.</p>
            <div class="iq-coverages">
                @foreach ($view['coverages'] as $coverage)
                    <button
                        type="button"
                        class="iq-choice"
                        x-bind:class="selected === @js($coverage['key']) && 'is-on'"
                        x-on:click="selected = @js($coverage['key'])"
                    >
                        <span>{{ $coverage['label'] }}</span>
                        <strong>{{ $coverage['annual_label'] }} <small>al año</small></strong>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    <section class="iq-card" aria-label="Rangos de edad">
        <h2>Rangos de edad</h2>
        <p class="iq-card__lead">Cómo se calcula: tarifa de cada rango × personas cotizadas.</p>

        @forelse ($view['ranges'] as $range)
            <article class="iq-range" wire:key="range-{{ $range['key'] }}">
                <header>
                    <h3>{{ $range['age_label'] }}</h3>
                    <p>{{ $range['persons_label'] }}</p>
                </header>
                <ul>
                    @foreach ($range['cells'] as $cell)
                        <li
                            @if ($view['mode'] === 'coverages')
                                x-bind:class="selected === @js($cell['coverage_key']) && 'is-on'"
                            @endif
                        >
                            <div>
                                <span>{{ $cell['coverage_label'] }}</span>
                                <small>{{ $cell['unit_label'] }} c/u</small>
                            </div>
                            <strong>{{ $cell['annual_label'] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <p class="iq-empty">No hay tarifas cargadas en esta cotización.</p>
        @endforelse
    </section>

    <section class="iq-card" aria-label="Formas de pago">
        <h2>Formas de pago</h2>
        <p class="iq-card__lead">El anual es el precio de referencia. Semestral y trimestral son el mismo total, en cuotas.</p>

        @if ($view['mode'] === 'coverages')
            @foreach ($view['coverages'] as $coverage)
                <div class="iq-pay" x-show="selected === @js($coverage['key'])" x-cloak>
                    <p class="iq-pay__label">{{ $coverage['label'] }}</p>
                    <ul>
                        @foreach ($coverage['frequencies'] as $frequency)
                            <li>
                                <span>
                                    {{ $frequency['label'] }}
                                    <small>{{ $frequency['hint'] }}</small>
                                </span>
                                <strong>{{ $frequency['amount_label'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @else
            <div class="iq-pay">
                <ul>
                    @foreach ($view['frequencies'] as $frequency)
                        <li>
                            <span>
                                {{ $frequency['label'] }}
                                <small>{{ $frequency['hint'] }}</small>
                            </span>
                            <strong>{{ $frequency['amount_label'] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <div class="iq-cta">
        <button type="button" class="iq-cta__btn" wire:click="download" wire:loading.attr="disabled">
            <span wire:loading.remove>Descargar cotización</span>
            <span wire:loading>Preparando PDF…</span>
        </button>
    </div>

    <style>
    [x-cloak] { display: none !important; }

    .iq-quote {
        min-height: 100dvh;
        max-width: 32rem;
        margin: 0 auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: calc(1rem + env(safe-area-inset-top, 0px)) 1rem calc(6.2rem + env(safe-area-inset-bottom, 0px));
        color: #f3eee6;
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Instrument Sans', system-ui, sans-serif;
    }

    .iq-quote__top {
        text-align: center;
        margin-bottom: 1rem;
    }

    .iq-quote__logo {
        height: 2.5rem;
        margin: 0 auto 0.85rem;
    }

    .iq-quote__kicker,
    .iq-quote__code {
        margin: 0;
        font-size: 0.68rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(243, 238, 230, 0.62);
    }

    .iq-quote__title {
        margin: 0.35rem 0 0.3rem;
        font-size: 1.7rem;
        font-weight: 720;
        letter-spacing: -0.04em;
        line-height: 1.1;
    }

    .iq-quote__meta {
        margin: 0;
        font-size: 0.92rem;
        color: rgba(243, 238, 230, 0.78);
    }

    .iq-hero,
    .iq-card {
        background: #f3eee6;
        color: #122033;
        border-radius: 1.35rem;
        padding: 1.05rem 1rem 1.1rem;
        margin-bottom: 0.8rem;
        box-shadow: 0 18px 40px rgba(2, 12, 28, 0.22);
    }

    .iq-hero__label,
    .iq-card h2,
    .iq-range header p,
    .iq-pay__label {
        margin: 0;
        font-size: 0.68rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: rgba(18, 32, 51, 0.55);
        font-weight: 650;
    }

    .iq-hero__amount {
        margin: 0.25rem 0 0.35rem;
        font-size: 1.65rem;
        font-weight: 750;
        letter-spacing: -0.045em;
        line-height: 1.1;
    }

    .iq-hero__hint,
    .iq-card__lead,
    .iq-empty {
        margin: 0.35rem 0 0;
        font-size: 0.88rem;
        line-height: 1.4;
        color: rgba(18, 32, 51, 0.68);
    }

    .iq-card h2 {
        font-size: 0.72rem;
    }

    .iq-coverages,
    .iq-range ul,
    .iq-pay ul {
        list-style: none;
        margin: 0.85rem 0 0;
        padding: 0;
        display: grid;
        gap: 0.55rem;
    }

    .iq-choice,
    .iq-range li,
    .iq-pay li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        text-align: left;
        border: 1px solid rgba(18, 32, 51, 0.08);
        background: rgba(255, 255, 255, 0.72);
        border-radius: 1rem;
        padding: 0.8rem 0.9rem;
        color: inherit;
    }

    .iq-choice {
        cursor: pointer;
    }

    .iq-choice.is-on,
    .iq-range li.is-on {
        border-color: #0d4f6e;
        box-shadow: inset 0 0 0 1px #0d4f6e;
        background: #fff;
    }

    .iq-choice span,
    .iq-range li span,
    .iq-pay li span {
        display: grid;
        gap: 0.12rem;
        font-size: 0.92rem;
        font-weight: 650;
    }

    .iq-choice small,
    .iq-range li small,
    .iq-pay li small {
        font-size: 0.72rem;
        font-weight: 500;
        color: rgba(18, 32, 51, 0.55);
    }

    .iq-choice strong,
    .iq-range li strong,
    .iq-pay li strong {
        font-size: 1rem;
        font-weight: 750;
        letter-spacing: -0.03em;
        white-space: nowrap;
    }

    .iq-range {
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px solid rgba(18, 32, 51, 0.08);
    }

    .iq-range:first-of-type {
        margin-top: 0.7rem;
    }

    .iq-range header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.55rem;
    }

    .iq-range h3 {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 720;
        letter-spacing: -0.03em;
    }

    .iq-cta {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 5;
        padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0px));
        background: linear-gradient(180deg, rgba(11, 31, 74, 0), rgba(11, 31, 74, 0.92) 28%, #0b1f4a);
    }

    .iq-cta__btn {
        display: flex;
        width: 100%;
        min-height: 3.2rem;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 1.1rem;
        background: #f3eee6;
        color: #122033;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        max-width: 32rem;
        margin-inline: auto;
    }

    .iq-cta__btn[disabled] {
        opacity: 0.6;
    }
    </style>
</div>
