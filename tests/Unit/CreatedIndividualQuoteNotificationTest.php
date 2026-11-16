<?php

declare(strict_types=1);

it('notifica analistas con imagen integracorp al crear cotizacion individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain('function createdIndividualQuote')
        ->toContain('SystemNotificationKey::NewQuoteAnalysts')
        ->toContain('WhatsAppBrandImage::publicUrl()')
        ->toContain('CURLOPT_URL_IMAGE')
        ->toContain('pre-afiliación del cliente')
        ->toContain('acompañamiento junto al agente')
        ->toContain('InternalMessaging::route(SystemNotificationKey::NewQuoteAnalysts')
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
        ->toContain('InternalMessaging::route(SystemNotificationKey::NewQuoteAnalysts')
        ->toContain('sendQuoteDocumentWhatsApp')
        ->toContain('ensureQuotePdfExists');
});

it('los analistas de cotizaciones arrancan con los mismos números que estaban fijos en el sistema', function (): void {
    /**
     * Desde la entrega B3b de Telegram los números salen de «Nuevas cotizaciones» del
     * Centro de notificaciones; su valor inicial son los dos teléfonos de tareas
     * programadas más la línea de cotizaciones y afiliaciones, como antes.
     */
    expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Enums/SystemNotificationKey.php'))
        ->toContain('self::NewQuoteAnalysts => self::phonesWithQuotesLine(ScheduledNotificationPhones::all())')
        ->toContain("config('parameters.PHONE_COTIZACIONES_AFILIACIONES')");
});
