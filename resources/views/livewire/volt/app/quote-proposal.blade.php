<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Quotes\InteractiveIndividualQuoteView;
use App\Support\Storefront\StorefrontQuotePdf;
use App\Support\Storefront\StorefrontQuoteShare;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Propuesta económica')] class extends Component
{
    public string $code = '';

    public string $negociosUrl = '';

    /**
     * @var array<string, mixed>
     */
    public array $view = [];

    /**
     * @var list<array{channel: string, value: string}>
     */
    public array $recipients = [];

    public function mount(string $code): void
    {
        $record = IndividualQuote::query()
            ->with(['detailsQuote.ageRange', 'detailsQuote.coverage'])
            ->where('code', $code)
            ->first();

        abort_unless($record instanceof IndividualQuote, 404);

        $plan = Plan::query()->find((int) $record->plan);

        abort_unless($plan instanceof Plan, 404);

        $this->code = (string) $record->code;
        $this->view = InteractiveIndividualQuoteView::from($record, $plan, $record->detailsQuote);
        $this->recipients = StorefrontQuoteShare::seedFromQuote($record);
        $this->negociosUrl = StorefrontQuoteShare::negociosWhatsAppUrl($this->code);
    }

    public function download()
    {
        $record = IndividualQuote::query()
            ->where('code', $this->code)
            ->first();

        abort_unless($record instanceof IndividualQuote, 404);

        if (! StorefrontQuotePdf::ensure($record)) {
            Flux::toast(
                heading: 'PDF no disponible',
                text: 'No pudimos armar el documento. Inténtalo de nuevo en unos segundos.',
                variant: 'danger',
            );

            return;
        }

        return response()->download(StorefrontQuotePdf::path($this->code), $this->code.'.pdf');
    }

    public function addRecipient(?string $channel = null): void
    {
        if (count($this->recipients) >= StorefrontQuoteShare::MAX_RECIPIENTS) {
            return;
        }

        $this->recipients[] = StorefrontQuoteShare::emptyRecipient(
            $channel ?? StorefrontQuoteShare::CHANNEL_WHATSAPP,
        );
    }

    public function addEmailRecipient(): void
    {
        $this->addRecipient(StorefrontQuoteShare::CHANNEL_EMAIL);
    }

    public function removeRecipient(int $index): void
    {
        unset($this->recipients[$index]);
        $this->recipients = array_values($this->recipients);

        if ($this->recipients === []) {
            $this->recipients[] = StorefrontQuoteShare::emptyRecipient();
        }
    }

    public function setRecipientChannel(int $index, string $channel): void
    {
        if (! isset($this->recipients[$index])) {
            return;
        }

        $next = StorefrontQuoteShare::channel($channel);
        $current = StorefrontQuoteShare::channel($this->recipients[$index]['channel'] ?? null);

        if ($next === $current) {
            return;
        }

        $recipients = $this->recipients;
        $recipients[$index]['channel'] = $next;
        $this->recipients = array_values($recipients);
    }

    public function share(): void
    {
        try {
            $ready = StorefrontQuoteShare::normalize($this->recipients);
            StorefrontQuoteShare::queue($this->code, $ready);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->js('window.dispatchEvent(new CustomEvent("storefront-quote-shared"));');
    }
}; ?>

<div
    class="sf-quote"
    x-data="{
        sheet: '',
        selected: @js($view['default_coverage_key']),
        dragY: 0,
        dragging: false,
        startY: 0,
        openCalc(key) {
            this.selected = key;
            this.sheet = 'calc';
            this.dragY = 0;
            this.dragging = false;
        },
        openSend() {
            this.sheet = 'send';
            this.dragY = 0;
        },
        closeSheet() {
            this.sheet = '';
            this.dragY = 0;
            this.dragging = false;
        },
        onSheetStart(event) {
            this.dragging = true;
            this.startY = event.touches[0].clientY;
        },
        onSheetMove(event) {
            if (! this.dragging) {
                return;
            }

            this.dragY = Math.max(0, event.touches[0].clientY - this.startY);
        },
        onSheetEnd() {
            if (this.dragY > 88) {
                this.closeSheet();
                return;
            }

            this.dragY = 0;
            this.dragging = false;
        },
    }"
    x-on:storefront-quote-shared.window="sheet = 'done'"
    x-on:keydown.escape.window="sheet !== '' && closeSheet()"
    x-effect="document.body.classList.toggle('is-quote-sheet-open', sheet !== '')"
>
    <p class="sf-back">
        <a href="{{ route('storefront.quote.result', $code) }}" wire:navigate>Volver a la cotización</a>
    </p>

    <section class="sf-hero">
        <p class="sf-kicker">Propuesta económica</p>
        <h1 class="sf-title">{{ $view['plan_title'] }}</h1>
        <p class="sf-lead">
            {{ $view['client_name'] }}
            @if ($view['date_label'] !== '')
                · {{ $view['date_label'] }}
            @endif
        </p>
        <p class="sf-lead" style="margin-top: 0.25rem;">{{ $view['code'] }} · {{ $view['agent_label'] }}</p>
    </section>

    <section class="sf-review sf-glass" aria-label="{{ $view['mode'] === 'coverages' ? 'Coberturas' : 'Tarifa cotizada' }}">
        <p class="sf-review__kicker">{{ $view['mode'] === 'coverages' ? 'Coberturas' : 'Tarifa cotizada' }}</p>
        <h2 class="sf-proposal__heading">{{ $view['mode'] === 'coverages' ? 'Elige cuánto cubre el plan' : 'Total del grupo' }}</h2>
        <p class="sf-review__note">
            {{ $view['mode'] === 'coverages'
                ? 'Toca una cobertura para ver el cálculo, descargar o enviarla.'
                : 'Toca para ver el cálculo, descargar o enviar la cotización.' }}
        </p>
        <div class="sf-proposal__choices">
            @foreach ($view['options'] as $option)
                <button
                    type="button"
                    class="sf-proposal__choice"
                    wire:key="option-{{ $option['key'] }}"
                    x-bind:class="selected === @js($option['key']) && 'is-on'"
                    x-on:click="openCalc(@js($option['key']))"
                >
                    <span>
                        {{ $option['label'] }}
                        <small>{{ $view['persons_label'] }}</small>
                    </span>
                    <strong>{{ $option['annual_label'] }} <small>al año</small></strong>
                </button>
            @endforeach
        </div>
    </section>

    <div class="sf-sticky-cta sf-sticky-cta--row">
        <button type="button" class="sf-btn sf-btn-ghost" x-on:click="openCalc(selected || @js($view['default_coverage_key']))">
            Ver
        </button>
        <button
            type="button"
            class="sf-btn"
            wire:click="download"
            wire:loading.attr="disabled"
            wire:target="download"
            wire:loading.class="is-busy"
        >
            @include('storefront.partials.btn-loading', ['target' => 'download', 'label' => 'Descargar cotización', 'wait' => 'Preparando PDF…'])
        </button>
    </div>

    <div
        class="sf-quote-sheet"
        x-cloak
        x-bind:class="sheet !== '' && 'is-open'"
        x-bind:aria-hidden="(sheet === '').toString()"
        x-bind:inert="sheet === ''"
        x-on:click.self="closeSheet()"
        role="dialog"
        aria-modal="true"
        x-bind:aria-labelledby="sheet === 'send' ? 'sf-share-title' : (sheet === 'done' ? 'sf-share-done-title' : 'sf-calc-title')"
    >
        <div
            class="sf-quote-sheet__panel is-tall"
            x-bind:style="(dragging || dragY) ? { transform: 'translateY(' + dragY + 'px)', transition: dragging ? 'none' : null } : null"
            x-on:click.stop
        >
            <div
                class="sf-quote-sheet__grab"
                x-on:touchstart.passive="onSheetStart($event)"
                x-on:touchmove.passive="onSheetMove($event)"
                x-on:touchend="onSheetEnd()"
            >
                <span class="sf-quote-sheet__handle" aria-hidden="true"></span>
            </div>

            <div class="sf-quote-sheet__body" x-show="sheet === 'calc'" x-cloak>
                <p class="sf-review__kicker">Cálculo</p>
                <h2 id="sf-calc-title" class="sf-proposal__heading">
                    @foreach ($view['options'] as $option)
                        <span x-show="selected === @js($option['key'])" x-cloak>{{ $option['label'] }}</span>
                    @endforeach
                </h2>
                <p class="sf-proposal__amount">
                    @foreach ($view['options'] as $option)
                        <span x-show="selected === @js($option['key'])" x-cloak>{{ $option['annual_label'] }} al año</span>
                    @endforeach
                </p>
                <p class="sf-review__note">{{ $view['persons_label'] }}. Tarifa de cada rango × las personas cotizadas.</p>

                <p class="sf-review__kicker" style="margin-top: 0.95rem;">Rangos de edad</p>

                @forelse ($view['ranges'] as $range)
                    <article class="sf-proposal__range" wire:key="range-{{ $range['key'] }}">
                        <header>
                            <h3>{{ $range['age_label'] }}</h3>
                            <p>{{ $range['persons_label'] }}</p>
                        </header>
                        <ul>
                            @foreach ($range['cells'] as $cell)
                                <li
                                    x-show="selected === @js($cell['coverage_key'])"
                                    x-cloak
                                >
                                    <div>
                                        <span>{{ $view['mode'] === 'coverages' ? $cell['coverage_label'] : 'Tarifa anual' }}</span>
                                        <small>{{ $cell['unit_label'] }} por persona</small>
                                    </div>
                                    <strong>{{ $cell['annual_label'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @empty
                    <p class="sf-review__note">No hay tarifas cargadas en esta cotización.</p>
                @endforelse

                <p class="sf-review__kicker" style="margin-top: 1.1rem;">Formas de pago</p>
                <p class="sf-review__note">El anual es el precio de referencia. Semestral y trimestral no cambian lo que pagas al año.</p>

                @foreach ($view['options'] as $option)
                    <div class="sf-proposal__pay" x-show="selected === @js($option['key'])" x-cloak>
                        <ul>
                            @foreach ($option['frequencies'] as $frequency)
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
            </div>

            <div class="sf-quote-sheet__actions" x-show="sheet === 'calc'" x-cloak>
                <button
                    type="button"
                    class="sf-btn"
                    wire:click="download"
                    wire:loading.attr="disabled"
                    wire:target="download"
                    wire:loading.class="is-busy"
                >
                    @include('storefront.partials.btn-loading', ['target' => 'download', 'label' => 'Descargar cotización', 'wait' => 'Preparando PDF…'])
                </button>
                <button type="button" class="sf-btn sf-btn-ghost" x-on:click="openSend()">
                    Enviar
                </button>
            </div>

            <div class="sf-quote-sheet__body" x-show="sheet === 'send'" x-cloak>
                <p class="sf-review__kicker">Destinatarios</p>
                <h2 id="sf-share-title" class="sf-proposal__heading">¿A quién se la enviamos?</h2>
                <p class="sf-review__note">Va por WhatsApp al teléfono de la cotización. Si quieres, cambia a correo o agrega uno.</p>

                @error('recipients')
                    <p class="sf-error">{{ $message }}</p>
                @enderror

                <div class="sf-share-list">
                    @foreach ($recipients as $index => $recipient)
                        @php
                            $channel = \App\Support\Storefront\StorefrontQuoteShare::channel($recipient['channel'] ?? null);
                            $isEmail = $channel === \App\Support\Storefront\StorefrontQuoteShare::CHANNEL_EMAIL;
                        @endphp
                        <div
                            class="sf-share-row"
                            wire:key="recipient-{{ $index }}-{{ $channel }}"
                        >
                            <div class="sf-seg" data-channel="{{ $channel }}" role="radiogroup" aria-label="Canal">
                                <button
                                    type="button"
                                    class="sf-seg__btn sf-seg__btn--whatsapp"
                                    role="radio"
                                    aria-checked="{{ $isEmail ? 'false' : 'true' }}"
                                    wire:click="setRecipientChannel({{ $index }}, 'whatsapp')"
                                >
                                    WhatsApp
                                </button>
                                <button
                                    type="button"
                                    class="sf-seg__btn sf-seg__btn--email"
                                    role="radio"
                                    aria-checked="{{ $isEmail ? 'true' : 'false' }}"
                                    wire:click="setRecipientChannel({{ $index }}, 'email')"
                                >
                                    Correo
                                </button>
                            </div>
                            <div class="sf-share-row__field">
                                <input
                                    type="{{ $isEmail ? 'email' : 'tel' }}"
                                    inputmode="{{ $isEmail ? 'email' : 'tel' }}"
                                    autocomplete="{{ $isEmail ? 'email' : 'tel' }}"
                                    placeholder="{{ $isEmail ? 'correo@ejemplo.com' : '0412 000 0000' }}"
                                    wire:model="recipients.{{ $index }}.value"
                                >
                                @error('recipients.'.$index.'.value')
                                    <p class="sf-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <button
                                type="button"
                                class="sf-share-row__remove"
                                wire:click="removeRecipient({{ $index }})"
                                aria-label="Quitar destinatario"
                            >
                                ×
                            </button>
                        </div>
                    @endforeach
                </div>

                @if (count($recipients) < 8)
                    <div class="sf-share-add-row">
                        <button type="button" class="sf-share-add" wire:click="addRecipient('whatsapp')">
                            Agregar otro WhatsApp
                        </button>
                        <button type="button" class="sf-share-add" wire:click="addEmailRecipient">
                            Agregar correo
                        </button>
                    </div>
                @endif
            </div>

            <div class="sf-quote-sheet__actions" x-show="sheet === 'send'" x-cloak>
                <button
                    type="button"
                    class="sf-btn"
                    wire:click="share"
                    wire:loading.attr="disabled"
                    wire:target="share"
                    wire:loading.class="is-busy"
                >
                    @include('storefront.partials.btn-loading', ['target' => 'share', 'label' => 'Enviar ahora', 'wait' => 'Enviando…'])
                </button>
                <button type="button" class="sf-btn sf-btn-ghost" x-on:click="sheet = 'calc'">
                    Volver al cálculo
                </button>
            </div>

            <div class="sf-quote-sheet__done" x-show="sheet === 'done'" x-cloak>
                <span class="sf-quote-sheet__seal" aria-hidden="true">✓</span>
                <h2 id="sf-share-done-title">La cotización va en camino</h2>
                <p>La estamos enviando a los destinos que confirmaste. Un asesor puede acompañarte si quieres afiliarte ahora.</p>
                <a
                    class="sf-btn"
                    href="{{ $negociosUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Hablar con negocios
                </a>
                <button type="button" class="sf-btn sf-btn-ghost" x-on:click="closeSheet()">
                    Listo
                </button>
            </div>
        </div>
    </div>
</div>
