<?php

declare(strict_types=1);

it('permite asignar color e icono en formulario de proyectos', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/Schemas/ProjectForm.php';
    $appearancePath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementProjectAppearance.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_28_140000_add_appearance_fields_to_projects_table.php';

    expect(file_exists($formPath))->toBeTrue();
    expect(file_exists($appearancePath))->toBeTrue();
    expect(file_exists($migrationPath))->toBeTrue();

    $formContent = file_get_contents($formPath);
    $appearanceContent = file_get_contents($appearancePath);
    $migrationContent = file_get_contents($migrationPath);

    expect($formContent)
        ->toContain("ColorPicker::make('color')")
        ->toContain("Select::make('icon')")
        ->toContain('Identidad visual')
        ->toContain('ProjectManagementProjectAppearance::iconOptions()');

    expect($appearanceContent)
        ->toContain('iconOptions')
        ->toContain('colorPresets')
        ->toContain('DEFAULT_COLOR')
        ->toContain('DEFAULT_ICON');

    expect($migrationContent)
        ->toContain("Schema::hasColumn('projects', 'color')")
        ->toContain("Schema::hasColumn('projects', 'icon')");
});
