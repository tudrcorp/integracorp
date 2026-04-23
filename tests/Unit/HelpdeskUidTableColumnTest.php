<?php

declare(strict_types=1);

it('muestra la columna uid en todas las tablas de helpdesk por panel', function (): void {
    foreach (['Business', 'Administration', 'Marketing', 'Operations'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $contents = file_get_contents($path);

        expect($contents)->toContain("TextColumn::make('uid')")
            ->toContain("->label('UID')");
    }
});

it('genera uid automáticamente en el modelo HelpDesk al crear un ticket', function (): void {
    $path = dirname(__DIR__, 2).'/app/Models/HelpDesk.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('static::creating(function (HelpDesk $helpDesk): void {')
        ->toContain('$helpDesk->uid = static::generateUniqueUid();')
        ->toContain('Str::upper((string) Str::ulid())');
});
