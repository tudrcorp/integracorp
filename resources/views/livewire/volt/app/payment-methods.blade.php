<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use App\Support\Storefront\StorefrontPaymentMethodsShare;
use App\Support\Storefront\StorefrontQuoteShare;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Métodos de pago')] class extends Component
{
    public string $downloadUrl = '';

    public bool $available = false;

    /**
     * @var list<array{channel: string, value: string}>
     */
    public array $recipients = [];

    public function mount(): void
    {
        $this->available = StorefrontPaymentMethodsDocument::exists();
        $this->downloadUrl = $this->available
            ? route('storefront.documents.payment-methods', ['download' => 1])
            : '';
        $this->recipients = StorefrontPaymentMethodsShare::seed();
    }

    public function addRecipient(?string $channel = null): void
    {
        if (count($this->recipients) >= StorefrontPaymentMethodsShare::MAX_RECIPIENTS) {
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
        if (! isset($this->recipients[$index])) {
            return;
        }

        unset($this->recipients[$index]);
        $this->recipients = array_values($this->recipients);

        if ($this->recipients === []) {
            $this->recipients = StorefrontPaymentMethodsShare::seed();
        }
    }

    public function setRecipientChannel(int $index, string $channel): void
    {
        if (! isset($this->recipients[$index])) {
            return;
        }

        $this->recipients[$index] = StorefrontQuoteShare::emptyRecipient(
            $channel,
            (string) ($this->recipients[$index]['value'] ?? ''),
        );
    }

    public function share(): void
    {
        if (! $this->available) {
            Flux::toast(
                heading: 'Documento no disponible',
                text: 'No encontramos el PDF de métodos de pago. Inténtalo más tarde.',
                variant: 'danger',
            );

            return;
        }

        try {
            $clean = StorefrontPaymentMethodsShare::normalize($this->recipients);
            StorefrontPaymentMethodsShare::queue($clean);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->dispatch('storefront-payment-methods-sent');
    }
}; ?>

<div
    class="sf-quote sf-quote--payments"
    x-data="{
        sheet: '',
        dragY: 0,
        dragging: false,
        startY: 0,
        downloadBusy: false,
        openSend() {
            this.sheet = 'send';
            document.body.classList.add('is-quote-sheet-open');
        },
        closeSheet() {
            this.sheet = '';
            this.dragY = 0;
            this.dragging = false;
            document.body.classList.remove('is-quote-sheet-open');
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
        async downloadPdf() {
            if (! @js($downloadUrl) || this.downloadBusy) {
                return;
            }

            this.downloadBusy = true;

            try {
                const response = await fetch(@js($downloadUrl), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/pdf' },
                });

                if (! response.ok) {
                    throw new Error('pdf-unavailable');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = @js(\App\Support\Storefront\StorefrontPaymentMethodsDocument::DOWNLOAD_FILENAME);
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (error) {
                window.location.assign(@js($downloadUrl));
            } finally {
                this.downloadBusy = false;
            }
        },
    }"
    x-on:keydown.escape.window="sheet !== '' && closeSheet()"
    x-on:storefront-payment-methods-sent.window="sheet = 'done'"
>
    <section class="sf-hero sf-hero--compact">
        <h1 class="sf-title">Métodos de pago</h1>
        <p class="sf-lead">Descárgalos o envíatelos por WhatsApp o correo en un momento.</p>
    </section>

    @if (! $available)
        <p class="sf-error">El documento no está disponible ahora. Vuelve a intentar en unos minutos.</p>
    @else
        <div class="sf-pay-actions">
            <button
                type="button"
                class="sf-pay-action"
                x-bind:disabled="downloadBusy"
                x-bind:class="downloadBusy && 'is-busy'"
                x-bind:aria-busy="downloadBusy.toString()"
                x-on:click="downloadPdf()"
            >
                <span class="sf-pay-action__label" x-show="! downloadBusy">Descargar métodos de pago</span>
                <span class="sf-pay-action__label sf-pay-action__busy" x-show="downloadBusy" x-cloak>
                    <span class="sf-spinner" aria-hidden="true"></span>
                    Preparando PDF…
                </span>
                <span class="sf-pay-action__hint">PDF listo · un solo toque</span>
            </button>

            <button type="button" class="sf-pay-action" x-on:click="openSend()">
                <span class="sf-pay-action__label">Reenviar métodos de pago</span>
                <span class="sf-pay-action__hint">WhatsApp y/o correo</span>
            </button>
        </div>
    @endif

    <div class="sf-sticky-cta">
        <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn sf-btn-ghost">Volver a planes</a>
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
        x-bind:aria-labelledby="sheet === 'done' ? 'sf-pay-done-title' : 'sf-pay-share-title'"
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

            <div class="sf-quote-sheet__body" x-show="sheet === 'send'" x-cloak>
                <p class="sf-review__kicker">Reenviar</p>
                <h2 id="sf-pay-share-title" class="sf-proposal__heading">¿A dónde lo enviamos?</h2>
                <p class="sf-review__note">Elige WhatsApp o correo. Puedes agregar más de un destino.</p>

                @error('recipients')
                    <p class="sf-error">{{ $message }}</p>
                @enderror

                <div class="sf-share-list">
                    @foreach ($recipients as $index => $recipient)
                        @php
                            $channel = StorefrontQuoteShare::channel($recipient['channel'] ?? null);
                            $isEmail = $channel === StorefrontQuoteShare::CHANNEL_EMAIL;
                        @endphp
                        <div class="sf-share-row" wire:key="pay-recipient-{{ $index }}-{{ $channel }}">
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

                @if (count($recipients) < StorefrontPaymentMethodsShare::MAX_RECIPIENTS)
                    <div class="sf-share-add-row">
                        <button type="button" class="sf-share-add" wire:click="addRecipient('whatsapp')">
                            Agregar WhatsApp
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
                <button type="button" class="sf-btn sf-btn-ghost" x-on:click="closeSheet()">
                    Cancelar
                </button>
            </div>

            <div class="sf-quote-sheet__done" x-show="sheet === 'done'" x-cloak>
                <span class="sf-quote-sheet__seal" aria-hidden="true">✓</span>
                <h2 id="sf-pay-done-title">Va en camino</h2>
                <p>Estamos enviando el PDF de métodos de pago a los destinos que confirmaste.</p>
                <button type="button" class="sf-btn" x-on:click="closeSheet()">
                    Listo
                </button>
            </div>
        </div>
    </div>
</div>
