<?php

declare(strict_types=1);

it('mantiene solrodriguez en CC y expone el armado de lista', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeMailService.php';
    $src = file_get_contents($path);

    expect($src)->toContain('solrodriguez@tudrencasa.com')
        ->and($src)->toContain('buildCcEmailListForAssigneeMessage')
        ->and($src)->toContain('->cc($ccList)')
        ->and($src)->toContain('loadTicketWithAssigneesForNotifications');
});

it('el trait de creación helpdesk valida correo corporativo de CC', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Concerns/PreparesHelpdeskColaboradorAssigneesOnCreate.php';
    $src = file_get_contents($path);

    expect($src)->toContain('helpdeskCcColaboradorIdsPendingValidation')
        ->and($src)->toContain('cc_colaboradores');
});
