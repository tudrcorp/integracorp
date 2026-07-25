<?php

declare(strict_types=1);

it('envia el mensaje post consulta al paciente con header de imagen Integracorp', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain('function previewMessage')
        ->toContain('sendWhatsAppBrandImageCaption')
        ->toContain('Esperamos que tu consulta de Telemedicina haya sido de gran ayuda')
        ->toContain('documentos generados por el médico durante la consulta');

    $previewMessageMethod = strstr($source, 'function previewMessage');
    $previewMessageMethod = strstr($previewMessageMethod, 'function sendDocumentsToPatient', true) ?: $previewMessageMethod;

    expect($previewMessageMethod)
        ->toContain('sendWhatsAppBrandImageCaption')
        ->not->toContain("'body' => \$body")
        ->not->toContain("config('parameters.CURLOPT_URL')");
});
