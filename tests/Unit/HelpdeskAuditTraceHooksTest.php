<?php

declare(strict_types=1);

it('registra auditoría en acciones modales de helpdesk para todos los paneles', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('AUDIT_HELPDESK_NOTE_ADDED')
            ->and($contents)->toContain('AUDIT_HELPDESK_NOTE_ADD_FAILED')
            ->and($contents)->toContain('AUDIT_HELPDESK_STATUS_UPDATED')
            ->and($contents)->toContain('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED');
    }
});

it('registra auditoría de creación y actualización de tickets en todos los paneles', function (): void {
    $createPaths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/CreateHelpdesk.php',
    ];
    $editPaths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/EditHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/EditHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/EditHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/EditHelpdesk.php',
    ];

    foreach ($createPaths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('AUDIT_HELPDESK_TICKET_CREATED')
            ->and($contents)->toContain('AUDIT_HELPDESK_TICKET_CREATE_FAILED');
    }

    foreach ($editPaths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('AUDIT_HELPDESK_TICKET_UPDATED')
            ->and($contents)->toContain('changed_fields');
    }
});

it('registra auditoría al marcar ticket en proceso desde business', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/Business/MarkHelpdeskTicketInProgressController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATED')
        ->and($contents)->toContain('AUDIT_HELPDESK_STATUS_UPDATE_FAILED')
        ->and($contents)->toContain('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED')
        ->and($contents)->toContain('business.helpdesk-ticket.mark-in-progress');
});
