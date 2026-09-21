<?php

declare(strict_types=1);

it('registra auditoría en acciones modales compartidas de helpdesk', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php');

    expect($shared)
        ->toContain('AUDIT_HELPDESK_NOTE_ADDED')
        ->toContain('AUDIT_HELPDESK_NOTE_ADD_FAILED')
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATED')
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED')
        ->toContain('AUDIT_HELPDESK_PRIORITY_UPDATED')
        ->toContain('AUDIT_HELPDESK_PRIORITY_UPDATE_SKIPPED')
        ->toContain('AUDIT_HELPDESK_PRIORITY_UPDATE_DENIED')
        ->toContain('AUDIT_HELPDESK_PRIORITY_UPDATE_FAILED')
        ->toContain('HelpdeskTicketAssigneeWhatsAppService::dispatchCustomMessageToEachAssigneeWithReport')
        ->toContain('HelpdeskTicketAssigneeWhatsAppService::dispatchToTicketCreatorWithReport')
        ->toContain('buildNoteAddedBody')
        ->toContain('buildStatusUpdatedBody')
        ->toContain('buildTicketClosedByCreatorBody')
        ->toContain('buildPriorityUpdatedByCreatorBody')
        ->toContain('notify_target')
        ->toContain('whatsapp_dispatched_count')
        ->toContain('notifications.whatsapp.note')
        ->toContain('notifications.whatsapp.status')
        ->toContain('notifications.whatsapp.priority');
});

it('cada panel delega acciones modales al shared con su identificador', function (string $panel, string $panelId): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php";
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('SharedHelpdeskTicketModalActions')
        ->toContain("makeAddNoteAction('{$panelId}')")
        ->toContain("makeUpdateStatusAction('{$panelId}')")
        ->toContain("makeUpdatePriorityAction('{$panelId}')")
        ->toContain("makeRevertToAnalystAction('{$panelId}')")
        ->toContain("makeAssignToSprintAction('{$panelId}')");
})->with([
    'Business' => ['Business', 'business'],
    'Administration' => ['Administration', 'administration'],
    'Marketing' => ['Marketing', 'marketing'],
    'Operations' => ['Operations', 'operations'],
]);

it('registra auditoría de creación y actualización de tickets en todos los paneles', function (): void {
    $createTrait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Concerns/DispatchesHelpdeskCreateNotifications.php');

    expect($createTrait)
        ->toContain('AUDIT_HELPDESK_TICKET_CREATED')
        ->toContain('AUDIT_HELPDESK_TICKET_CREATE_FAILED');

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
            ->toContain('DispatchesHelpdeskCreateNotifications')
            ->toContain('dispatchHelpdeskCreateNotifications');
    }

    foreach ($editPaths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('AUDIT_HELPDESK_TICKET_UPDATED')
            ->toContain('changed_fields');
    }
});

it('registra auditoría al marcar ticket en proceso desde business', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/Business/MarkHelpdeskTicketInProgressController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATED')
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATE_FAILED')
        ->toContain('AUDIT_HELPDESK_STATUS_UPDATE_SKIPPED')
        ->toContain('business.helpdesk-ticket.mark-in-progress');
});
