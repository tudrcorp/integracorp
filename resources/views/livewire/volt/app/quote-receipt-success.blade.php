<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontQuoteReceipt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Comprobante enviado')] class extends Component
{
    public string $code = '';

    /**
     * @var array{label: string, hint: string, url: string|null}
     */
    public array $whatsapp = [
        'label' => 'Escribir a Administración',
        'hint' => '',
        'url' => null,
    ];

    public function mount(string $code): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);

            return;
        }

        $record = StorefrontQuoteReceipt::findOwned($user, $code);

        if ($record->paymentReceipts->isEmpty()) {
            $this->redirect(route('storefront.quote.receipt', ['code' => $record->code]), navigate: true);

            return;
        }

        $this->code = (string) $record->code;
        $this->whatsapp = StorefrontQuoteReceipt::administrationWhatsApp($this->code);
    }
}; ?>

<div class="sf-receipt-ok">
    <section class="sf-hero sf-hero--compact sf-receipt-ok__hero">
        <span class="sf-receipt-ok__seal" aria-hidden="true">
            <svg viewBox="0 0 96 96" fill="none">
                <path fill="#cceed9" d="M48 4.5 55.4 11l10.4-1.6 4.6 9.5 10.1 2.2.6 10.3 9.5 4.6-1.6 10.4L96 48l-6.5 7.4 1.6 10.4-9.5 4.6-.6 10.3-10.1 2.2-4.6 9.5-10.4-1.6L48 91.5 40.6 85l-10.4 1.6-4.6-9.5-10.1-2.2-.6-10.3-9.5-4.6 1.6-10.4L0 48l6.5-7.4-1.6-10.4 9.5-4.6.6-10.3 10.1-2.2 4.6-9.5 10.4 1.6z" />
                <path d="M30 50.5 42.5 63 67 34" stroke="#0b6b45" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <p class="sf-kicker">Listo</p>
        <h1 class="sf-title">Comprobante cargado</h1>
        <p class="sf-lead">
            El comprobante de la cotización <strong>{{ $code }}</strong> se envió con éxito.
            Administración lo revisará a la brevedad.
        </p>
    </section>

    <div class="sf-receipt-ok__actions">
        <a href="{{ route('storefront.quotes') }}" wire:navigate class="sf-btn">Volver a cotizaciones</a>
        @if (($whatsapp['url'] ?? null) !== null)
            <a
                href="{{ $whatsapp['url'] }}"
                class="sf-btn sf-btn-ghost sf-receipt-ok__wa"
                target="_blank"
                rel="noopener noreferrer"
            >
                {{ $whatsapp['label'] }}
            </a>
            @if (($whatsapp['hint'] ?? '') !== '')
                <p class="sf-receipt-ok__hint">{{ $whatsapp['hint'] }}</p>
            @endif
        @else
            <p class="sf-receipt-ok__hint">Cuando Administración configure su WhatsApp, podrás escribirles desde aquí.</p>
        @endif
    </div>
</div>
