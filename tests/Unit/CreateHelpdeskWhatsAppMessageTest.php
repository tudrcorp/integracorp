<?php

declare(strict_types=1);

it('el mensaje de WhatsApp al asignar ticket incluye los datos solicitados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/CreateHelpdesk.php';
    $src = file_get_contents($path);

    expect($src)->toContain('Le han asignado un ticket de soporte en INTEGRACORP.')
        ->and($src)->toContain('Creado por:')
        ->and($src)->toContain('Fecha y hora:')
        ->and($src)->toContain('*Prioridad: {$priorityLabel}*')
        ->and($src)->toContain('*{$description}*')
        ->and($src)->toContain('Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para dar inicio a la gestión del ticket.')
        ->and($src)->toContain('loadTicketWithAssigneesForNotifications');
});
