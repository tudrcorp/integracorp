<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementActivityAppearance;

it('permite asignar color en formulario de actividades y usarlo en kanban', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Schemas/ActivityForm.php';
    $appearancePath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementActivityAppearance.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_28_150000_add_color_to_activities_table.php';
    $kanbanViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';
    $activityTablePath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementActivityTable.php';

    expect(file_exists($formPath))->toBeTrue();
    expect(file_exists($appearancePath))->toBeTrue();
    expect(file_exists($migrationPath))->toBeTrue();

    expect(file_get_contents($formPath))
        ->toContain('Identidad visual')
        ->toContain("ColorPicker::make('color')")
        ->toContain('ProjectManagementActivityAppearance::colorPresets()');

    expect(file_get_contents($migrationPath))
        ->toContain("Schema::hasColumn('activities', 'color')");

    expect(file_get_contents($kanbanViewPath))
        ->toContain('ProjectManagementActivityTable::resolveColor($activity)')
        ->toContain('$activityColor');

    expect(file_get_contents($activityTablePath))
        ->toContain('filled($activity->color)');

    expect(ProjectManagementActivityAppearance::DEFAULT_COLOR)->toBe('#8B5CF6');
});
