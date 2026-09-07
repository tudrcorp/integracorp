<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Models\IndividualQuote;
use App\Models\IndividualQuotePaymentReceipt;
use App\Models\User;
use App\Support\Storefront\StorefrontQuotePaymentReceiptNotificationMessage;
use App\Support\Storefront\StorefrontQuoteReceipt;

function storefrontReceiptPath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('expone el tipo de notificacion de comprobante pwa en el centro de notificaciones', function (): void {
    $enum = file_get_contents(storefrontReceiptPath('app/Enums/SystemNotificationKey.php'));
    $migration = file_get_contents(storefrontReceiptPath('database/migrations/2026_09_04_115412_add_storefront_quote_payment_receipt_notification_recipient_setting.php'));

    expect($enum)
        ->toContain("case StorefrontQuotePaymentReceipt = 'storefront_quote_payment_receipt'")
        ->toContain("self::StorefrontQuotePaymentReceipt => 'Comprobante de pago PWA'");

    expect(SystemNotificationKey::StorefrontQuotePaymentReceipt->label())
        ->toBe('Comprobante de pago PWA')
        ->and(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::StorefrontQuotePaymentReceipt)
        ->and(SystemNotificationKey::StorefrontQuotePaymentReceipt->pausesScheduledTask())
        ->toBeFalse();

    expect($migration)
        ->toContain('SystemNotificationKey::StorefrontQuotePaymentReceipt');
});

it('el flujo de comprobante de la pwa guarda el archivo y notifica a administracion en cola', function (): void {
    $receipt = file_get_contents(storefrontReceiptPath('app/Support/Storefront/StorefrontQuoteReceipt.php'));
    $notifier = file_get_contents(storefrontReceiptPath('app/Support/Storefront/StorefrontQuotePaymentReceiptNotifier.php'));
    $job = file_get_contents(storefrontReceiptPath('app/Jobs/NotifyAdministrationOfStorefrontQuotePaymentReceiptJob.php'));
    $mail = file_get_contents(storefrontReceiptPath('app/Mail/StorefrontQuotePaymentReceiptMail.php'));
    $table = file_get_contents(storefrontReceiptPath('database/migrations/2026_09_04_115411_create_individual_quote_payment_receipts_table.php'));
    $model = file_get_contents(storefrontReceiptPath('app/Models/IndividualQuote.php'));

    expect($receipt)
        ->toContain('storeAs')
        ->toContain('StorefrontQuotePaymentReceiptNotifier::notify')
        ->toContain('paymentFromDetails')
        ->toContain("channel = 'pwa'");

    expect($notifier)
        ->toContain('NotifyAdministrationOfStorefrontQuotePaymentReceiptJob::dispatch')
        ->and($job)->toContain('implements ShouldBeUnique, ShouldQueue')
        ->and($job)->toContain('SystemNotificationKey::StorefrontQuotePaymentReceipt')
        ->and($job)->toContain('SystemNotificationRecipients::emails')
        ->and($job)->toContain('SystemNotificationRecipients::phones')
        ->and($job)->toContain('SendNotificacionWhatsApp')
        ->and($mail)->toContain('mails.storefront-quote-payment-receipt')
        ->and($mail)->toContain('Attachment::fromPath')
        ->and($table)->toContain('individual_quote_payment_receipts')
        ->and($table)->toContain('storefront_user_id')
        ->and($model)->toContain('function paymentReceipts');
});

it('las pantallas de pagar y cargar comprobante son fluidas y no pierden la propuesta', function (): void {
    $quotes = file_get_contents(storefrontReceiptPath('resources/views/livewire/volt/app/quotes.blade.php'));
    $frequency = file_get_contents(storefrontReceiptPath('resources/views/livewire/volt/app/quote-frequency.blade.php'));
    $pay = file_get_contents(storefrontReceiptPath('resources/views/livewire/volt/app/quote-pay.blade.php'));
    $upload = file_get_contents(storefrontReceiptPath('resources/views/livewire/volt/app/quote-receipt.blade.php'));
    $success = file_get_contents(storefrontReceiptPath('resources/views/livewire/volt/app/quote-receipt-success.blade.php'));
    $routes = file_get_contents(storefrontReceiptPath('routes/storefront.php'));
    $css = file_get_contents(storefrontReceiptPath('resources/css/storefront.css'));
    $nav = file_get_contents(storefrontReceiptPath('app/Support/Storefront/StorefrontNav.php'));

    expect($quotes)
        ->toContain('pay_url')
        ->toContain('sf-quote-card__hit')
        ->toContain('Ver propuesta')
        ->toContain('PDF')
        ->and($frequency)->toContain('¿Cada cuánto quieres pagar?')
        ->and($frequency)->toContain('StorefrontQuoteFrequency::choices')
        ->and($frequency)->toContain('sf-freq')
        ->and($pay)->toContain('Próximamente')
        ->and($pay)->toContain('Cargar comprobante de pago')
        ->and($pay)->toContain('sf-pay__calc')
        ->and($pay)->toContain('breakdown')
        ->and($pay)->toContain('is-soon')
        ->and($upload)->toContain('storefront.partials.quote-plan-row')
        ->and($upload)->toContain('WithFileUploads')
        ->and($upload)->toContain('capture="environment"')
        ->and($upload)->toContain('Buscar en el teléfono')
        ->and($upload)->toContain('Usar cámara')
        ->and($upload)->toContain('Enviar comprobante')
        ->and($upload)->toContain('Documento cargado con éxito')
        ->and($upload)->toContain('sf-receipt__ok')
        ->and($upload)->toContain('sf-receipt__ok-icon')
        ->and($upload)->toContain('viewBox="0 0 20 20"')
        ->and($upload)->not->toContain('Flux::toast')
        ->and($css)->toContain('.sf-receipt__ok')
        ->and($css)->toContain('.sf-receipt__ok-icon svg')
        ->and($css)->not->toContain('.sf-receipt__ok span')
        ->and($upload)->toContain('x-show="uploading"')
        ->and($upload)->toContain('livewire-upload-finish')
        ->and($upload)->not->toContain('wire:target="receipt"')
        ->and($upload)->not->toContain('wire:loading.delay.150ms')
        ->and($success)->toContain('Comprobante cargado')
        ->and($success)->toContain('Volver a cotizaciones')
        ->and($success)->toContain('Escribir a Administración')
        ->and($routes)->toContain('volt.app.quote-coverages')
        ->and($routes)->toContain('volt.app.quote-frequency')
        ->and($routes)->toContain('volt.app.quote-pay')
        ->and($routes)->toContain('volt.app.quote-receipt')
        ->and($routes)->toContain('volt.app.quote-receipt-success')
        ->and($css)->toContain('.sf-pay__choice')
        ->and($css)->toContain('.sf-receipt__picker')
        ->and($nav)->toContain("'storefront.quote.pay' => ''");
});

it('el aviso a administracion nombra cotizacion usuario y canal pwa', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-0003663',
        'full_name' => 'GUSTAVO',
        'phone' => '04127018390',
        'email' => 'fa@tes.com',
        'status' => 'PRE-APROBADA',
    ]);
    $receipt = new IndividualQuotePaymentReceipt([
        'original_name' => 'comprobante.jpg',
        'mime' => 'image/jpeg',
        'channel' => 'pwa',
    ]);
    $user = new User([
        'name' => 'Gustavo Camacho',
        'email' => 'gustavo@example.com',
        'phone' => '04127018390',
        'nro_identification' => 'V123',
    ]);

    $body = StorefrontQuotePaymentReceiptNotificationMessage::whatsappBody($quote, $receipt, $user);

    expect($body)
        ->toContain('COT-IND-0003663')
        ->toContain('Gustavo Camacho')
        ->toContain('canal PWA')
        ->and(StorefrontQuotePaymentReceiptNotificationMessage::emailSubject($quote))
        ->toContain('COT-IND-0003663')
        ->and(StorefrontQuoteReceipt::MIMES)
        ->toContain('jpg')
        ->toContain('pdf')
        ->toContain('heic');
});
