<?php

declare(strict_types=1);

it('TelemedicineCaseDocumentDeliveryService expone envio por whatsapp y correo', function (): void {
    $serviceContents = file_get_contents(dirname(__DIR__, 2).'/app/Services/Telemedicine/TelemedicineCaseDocumentDeliveryService.php');
    $actionContents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentSendAction.php');

    expect($serviceContents)
        ->toContain('public static function send(')
        ->toContain('sendWhatsApp')
        ->toContain('sendEmail');

    expect($actionContents)
        ->toContain('TelemedicineCaseDocumentDeliveryService::send')
        ->toContain("Action::make('sendCaseDocument')");

    $controllerContents = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($controllerContents)->toContain('sendPublicStorageDocumentWhatsApp');
});
