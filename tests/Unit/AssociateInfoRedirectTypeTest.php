<?php

declare(strict_types=1);

it('no tipa el bulk action associateInfo como RedirectResponse (InfoFrees)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/InfoFrees/Tables/InfoFreesTable.php';
    $src = file_get_contents($path);

    expect($src)->not->toContain('): \\Illuminate\\Http\\RedirectResponse');
});

it('no tipa el bulk action associateInfo como RedirectResponse (Capemiacs)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Capemiacs/Tables/CapemiacsTable.php';
    $src = file_get_contents($path);

    expect($src)->not->toContain('): \\Illuminate\\Http\\RedirectResponse');
});

it('incluye el bulk action associateInfo (Agencies)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Agencies/Tables/AgenciesTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain("BulkAction::make('associateInfo')");
});

it('incluye el bulk action associateInfo (Agents)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Agents/Tables/AgentsTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain("BulkAction::make('associateInfo')");
});

it('MassNotificationForm permite seleccionar fecha y hora programada', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Schemas/MassNotificationForm.php';
    $src = file_get_contents($path);

    expect($src)->toContain("TextInput::make('date_programed')")
        ->and($src)->toContain("->type('datetime-local')");
});
