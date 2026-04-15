<?php

declare(strict_types=1);

it('solo el creador puede editar el ticket según cada recurso helpdesk', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";
    $src = file_get_contents($path);

    expect($src)->toContain('currentUserIsHelpdeskTicketCreator')
        ->and($src)->toContain('public static function canEdit');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('la tabla oculta Editar salvo para el creador en cada panel', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
    $src = file_get_contents($path);

    expect($src)->toContain('HelpdeskResource::currentUserIsHelpdeskTicketCreator');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
