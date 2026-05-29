<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;
use App\Support\Filament\ProjectManagement\ProjectManagementSubprojectTable;

it('define tablas premium de subproyectos y actividades', function (): void {
    $basePath = dirname(__DIR__, 2);

    $subprojectsTable = file_get_contents($basePath.'/app/Filament/Projects/Resources/ProjectManagement/Subprojects/Tables/SubprojectsTable.php');
    $activitiesTable = file_get_contents($basePath.'/app/Filament/Projects/Resources/ProjectManagement/Activities/Tables/ActivitiesTable.php');

    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/subproject-identity.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/subproject-workload.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/activity-identity.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/activity-due.blade.php'))->toBeTrue();

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/activity-due.blade.php'))
        ->toContain('linear-gradient(90deg, #22c55e')
        ->toContain('role="progressbar"');
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/activity-assignment.blade.php'))->toBeTrue();

    expect($subprojectsTable)
        ->toContain('ViewColumn::make(\'subproject_identity\')')
        ->toContain('ViewColumn::make(\'workload\')')
        ->toContain('activities_done_count')
        ->toContain('->recordUrl(')
        ->toContain('->paginated([10, 25, 50])');

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/activity-identity.blade.php'))
        ->toContain('fi-projects-activity-identity')
        ->toContain('fi-projects-activity-identity__description')
        ->toContain('line-clamp-2')
        ->toContain('normalizeDescriptionText');

    expect($activitiesTable)
        ->toContain('ViewColumn::make(\'activity_identity\')')
        ->toContain('->grow()')
        ->toContain('extraCellAttributes')
        ->toContain('ViewColumn::make(\'due_timeline\')')
        ->toContain('fi-projects-activities-due-cell')
        ->toContain('min-w-[18rem]')
        ->toContain('ViewColumn::make(\'assignment\')')
        ->toContain('fi-projects-activities-assignment-cell')
        ->toContain('Filter::make(\'vencidas\')')
        ->toContain('->with([\'project\', \'subproject\', \'executor\'])')
        ->toContain('->paginated([10, 25, 50])');

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/activity-assignment.blade.php'))
        ->toContain('fi-projects-activity-assignment')
        ->toContain('ps-4');

    expect(ProjectManagementSubprojectTable::statusMeta('active'))
        ->toBe(['label' => 'Activo', 'color' => 'success']);

    expect(ProjectManagementActivityTable::statusMeta('in_progress'))
        ->toBe(['label' => 'En progreso', 'color' => 'info']);

    expect(ProjectManagementActivityTable::priorityMeta('high'))
        ->toBe(['label' => 'Alta', 'color' => 'danger']);
});
