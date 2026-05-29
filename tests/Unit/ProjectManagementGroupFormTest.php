<?php

declare(strict_types=1);

it('incluye selector multiple de colaboradores en formulario de grupos', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Groups/Schemas/GroupForm.php';

    expect(file_exists($formPath))->toBeTrue();

    $content = file_get_contents($formPath);

    expect($content)
        ->toContain("Select::make('collaborator_ids')")
        ->toContain('->multiple()')
        ->toContain("->where('fullName', '!=', 'CAYETANO BATRES')")
        ->toContain('RrhhColaborador::query()')
        ->toContain('->default([])');
});
