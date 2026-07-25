<?php

declare(strict_types=1);

it('envia la asignacion de caso con header de imagen Integracorp', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain('function assignedCase')
        ->toContain('sendWhatsAppBrandImageCaption')
        ->toContain('acaba de ser asignado a tu equipo')
        ->toContain('Motivo de la Consulta')
        ->toContain('https://integracorp.tudrgroup.com/telemedicina');

    $assignedCaseMethod = strstr($source, 'function assignedCase');
    $assignedCaseMethod = strstr($assignedCaseMethod, 'function notificationVideo', true) ?: $assignedCaseMethod;

    expect($assignedCaseMethod)
        ->toContain('sendWhatsAppBrandImageCaption')
        ->not->toContain("'body' => \$body")
        ->not->toContain("config('parameters.CURLOPT_URL')");
});
