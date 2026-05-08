<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('configura el guardado explícito de múltiples asignados en HelpdeskForm de cada panel', function (): void {
    $panels = ['Business', 'Administration', 'Marketing', 'Operations'];

    foreach ($panels as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Schemas/HelpdeskForm.php";
        $src = file_get_contents($path);

        expect($src)->toContain('->multiple()')
            ->and($src)->toContain("name: 'rrhhColaboradores'")
            ->and($src)->toContain('->saveRelationshipsUsing(function (HelpDesk $record, ?array $state): void {')
            ->and($src)->toContain('->rrhhColaboradores()->sync($state ?? []);');
    }
});
