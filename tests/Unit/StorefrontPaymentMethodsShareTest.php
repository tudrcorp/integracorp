<?php

declare(strict_types=1);

it('la pantalla de metodos de pago descarga y reenvia por cola', function (): void {
    $base = dirname(__DIR__, 2);
    $page = file_get_contents($base.'/resources/views/livewire/volt/app/payment-methods.blade.php');
    $share = file_get_contents($base.'/app/Support/Storefront/StorefrontPaymentMethodsShare.php');
    $job = file_get_contents($base.'/app/Jobs/SendStorefrontPaymentMethodsShareJob.php');
    $mail = file_get_contents($base.'/app/Mail/StorefrontPaymentMethodsMail.php');
    $nav = file_get_contents($base.'/app/Support/Storefront/StorefrontNav.php');

    expect($page)
        ->toContain('Descargar métodos de pago')
        ->toContain('Reenviar métodos de pago')
        ->toContain('downloadPdf')
        ->toContain('StorefrontPaymentMethodsShare::queue')
        ->toContain('sf-pay-actions')
        ->and($share)->toContain('SendStorefrontPaymentMethodsShareJob::dispatch')
        ->and($job)->toContain('ShouldBeUnique, ShouldQueue')
        ->and($job)->toContain('sendPublicStorageDocumentWhatsApp')
        ->and($job)->toContain('StorefrontPaymentMethodsMail')
        ->and($mail)->toContain('Métodos de pago — Tu Dr En Casa')
        ->and($nav)->toContain("'payments', 'Métodos de pago'")
        ->and($nav)->toContain("'storefront.payment-methods'");
});
