<?php

declare(strict_types=1);

it('EditHelpdesk registra acciones de nota y estado en el encabezado para cada panel', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/EditHelpdesk.php";
    $contents = file_get_contents($path);

    expect($contents)->toContain('HelpdeskTicketModalActions::makeAddNoteAction')
        ->toContain('HelpdeskTicketModalActions::makeUpdateStatusAction')
        ->toContain('getHeaderActions');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
