<?php

declare(strict_types=1);

it('notifica analistas con imagen integracorp al crear cotizacion individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain('function createdIndividualQuote')
        ->toContain('ScheduledNotificationPhones::all()')
        ->toContain('WhatsAppBrandImage::publicUrl()')
        ->toContain('CURLOPT_URL_IMAGE')
        ->toContain('pre-afiliación del cliente')
        ->toContain('acompañamiento junto al agente')
        ->toContain('quoteAnalystRecipientPhones')
        ->toContain('sendIndividualQuotePdfToAnalyst')
        ->toContain('sendPublicStorageDocumentWhatsApp')
        ->toContain('sendQuoteDocumentWhatsApp');
});

it('notifica analistas con imagen integracorp al crear cotizacion corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain('function createdCorporateQuote')
        ->toContain('*INTEGRACORP · Cotización Corporativa*')
        ->toContain('cotización corporativa')
        ->toContain('sendCorporateQuotePdfToAnalyst')
        ->toContain('quoteAnalystRecipientPhones')
        ->toContain('sendQuoteDocumentWhatsApp')
        ->toContain('ensureQuotePdfExists');
});
