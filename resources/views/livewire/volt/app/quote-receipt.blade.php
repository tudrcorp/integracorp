<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontQuoteCoverages;
use App\Support\Storefront\StorefrontQuoteFrequency;
use App\Support\Storefront\StorefrontQuoteReceipt;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.storefront')] #[Title('Cargar comprobante')] class extends Component
{
    use WithFileUploads;

    public string $code = '';

    public string $frequency = StorefrontQuoteFrequency::Annual;

    /**
     * @var array<string, mixed>
     */
    public array $quote = [];

    public $receipt = null;

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

    public function updatedReceipt(): void
    {
        $this->resetErrorBag('receipt');

        if ($this->receipt === null) {
            return;
        }

        $this->validateOnly('receipt', $this->receiptRules(), $this->receiptMessages());
    }

    public function submit(): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        $this->validate($this->receiptRules(), $this->receiptMessages());

        if (! $this->receipt instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'receipt' => ['Elige una foto o un archivo del comprobante.'],
            ]);
        }

        $record = StorefrontQuoteReceipt::findOwned($user, $this->code);
        StorefrontQuoteReceipt::store($record, $user, $this->receipt);

        $this->redirect(
            route('storefront.quote.receipt.success', ['code' => $this->code]),
            navigate: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptRules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'max:'.StorefrontQuoteReceipt::MAX_KILOBYTES,
                'mimes:'.implode(',', StorefrontQuoteReceipt::MIMES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function receiptMessages(): array
    {
        return [
            'receipt.required' => 'Elige una foto o un archivo del comprobante.',
            'receipt.file' => 'Elige una foto o un archivo del comprobante.',
            'receipt.max' => 'El archivo debe pesar menos de 8 MB.',
            'receipt.mimes' => 'Usa una foto (JPG, PNG, HEIC) o un PDF del comprobante.',
        ];
    }
}; ?>

<div
    class="sf-receipt"
    x-data="{ uploading: false }"
    x-on:livewire-upload-start="uploading = true"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
>
    <section class="sf-hero sf-hero--compact">
        <p class="sf-kicker">Comprobante</p>
        <h1 class="sf-title">Carga tu pago</h1>
        <p class="sf-lead">Toma una foto clara o busca el archivo en el teléfono. Administración lo recibe al instante.</p>
    </section>

    <article class="sf-pay__summary sf-glass">
        <div class="sf-quote-card__top">
            <p class="sf-quote-card__code">{{ $quote['code'] ?? '' }}</p>
            <span class="sf-quote-card__status is-{{ $quote['status_tone'] ?? 'mute' }}">{{ $quote['status'] ?? '' }}</span>
        </div>
        <h2 class="sf-quote-card__client">{{ $quote['client'] ?? 'Sin nombre' }}</h2>
        @include('storefront.partials.quote-plan-row', ['quote' => $quote])
        @if (($quote['coverages_label'] ?? '') !== '')
            <p class="sf-pay__coverages">{{ $quote['coverages_label'] }}</p>
        @endif
        @if (($quote['breakdown'] ?? '') !== '')
            <p class="sf-pay__receipt-note">{{ $quote['breakdown'] }}</p>
        @endif
    </article>

    <section class="sf-receipt__upload sf-glass">
        <p class="sf-receipt__label">Comprobante de pago</p>

        <div class="sf-receipt__pickers">
            <label class="sf-receipt__picker" for="sf-receipt-camera">
                <input
                    id="sf-receipt-camera"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    wire:model="receipt"
                    x-on:livewire-upload-start="uploading = true"
                    x-on:livewire-upload-finish="uploading = false"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-cancel="uploading = false"
                >
                <span class="sf-receipt__picker-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4.75 8.25h2.2l1.1-1.8h8l1.1 1.8h2.1A2.25 2.25 0 0 1 21.5 10.5v6.75A2.25 2.25 0 0 1 19.25 19.5H4.75A2.25 2.25 0 0 1 2.5 17.25V10.5A2.25 2.25 0 0 1 4.75 8.25Z" />
                        <circle cx="12" cy="13.5" r="3.1" />
                    </svg>
                </span>
                <strong>Usar cámara</strong>
                <span>Foto del voucher o transferencia</span>
            </label>

            <label class="sf-receipt__picker" for="sf-receipt-gallery">
                <input
                    id="sf-receipt-gallery"
                    type="file"
                    accept="image/*,application/pdf"
                    wire:model="receipt"
                    x-on:livewire-upload-start="uploading = true"
                    x-on:livewire-upload-finish="uploading = false"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-cancel="uploading = false"
                >
                <span class="sf-receipt__picker-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 3.75h7.5L19.25 8.5V18A2.25 2.25 0 0 1 17 20.25H7A2.25 2.25 0 0 1 4.75 18V6A2.25 2.25 0 0 1 7 3.75Z" />
                        <path d="M14.5 3.75V8.5h4.75" />
                    </svg>
                </span>
                <strong>Buscar en el teléfono</strong>
                <span>Galería o archivo PDF</span>
            </label>
        </div>

        <div class="sf-receipt__busy" x-show="uploading" x-cloak>
            <span class="sf-spinner" aria-hidden="true"></span>
            Preparando archivo…
        </div>

        @if ($receipt)
            @unless ($errors->has('receipt'))
                <div class="sf-receipt__ok" role="status" aria-live="polite">
                    <span class="sf-receipt__ok-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none">
                            <path d="M5 10.5 8.5 14 15 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div class="sf-receipt__ok-copy">
                        <strong>Documento cargado con éxito</strong>
                        <p>El archivo está listo. Pulsa Enviar comprobante para que Administración lo reciba.</p>
                    </div>
                </div>
            @endunless
            <div class="sf-receipt__preview">
                @if (is_object($receipt) && method_exists($receipt, 'isPreviewable') && $receipt->isPreviewable())
                    <img src="{{ $receipt->temporaryUrl() }}" alt="Vista previa del comprobante">
                @else
                    <p class="sf-receipt__filename">{{ is_object($receipt) ? $receipt->getClientOriginalName() : 'Archivo seleccionado' }}</p>
                @endif
            </div>
        @endif

        @error('receipt')
            <p class="sf-error">{{ $message }}</p>
        @enderror
    </section>

    <div class="sf-sticky-cta">
        <button
            type="button"
            class="sf-btn"
            wire:click="submit"
            wire:loading.attr="disabled"
            wire:target="submit"
            wire:loading.class="is-busy"
            x-bind:disabled="uploading || {{ $receipt ? 'false' : 'true' }}"
        >
            @include('storefront.partials.btn-loading', ['target' => 'submit', 'label' => 'Enviar comprobante', 'wait' => 'Enviando…'])
        </button>
    </div>
</div>
