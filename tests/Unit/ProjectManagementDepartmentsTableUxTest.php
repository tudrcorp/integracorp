<?php

declare(strict_types=1);

it('define tabla premium de departamentos con identidad y carga', function (): void {
    $basePath = dirname(__DIR__, 2);
    $tablePath = $basePath.'/app/Filament/Projects/Resources/ProjectManagement/Departments/Tables/DepartmentsTable.php';
    $helperPath = $basePath.'/app/Support/Filament/ProjectManagement/ProjectManagementDepartmentTable.php';

    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/department-identity.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/department-workload.blade.php'))->toBeTrue();

    expect(file_get_contents($tablePath))
        ->toContain('ViewColumn::make(\'department_identity\')')
        ->toContain('ViewColumn::make(\'department_workload\')')
        ->toContain('executed_activities_count')
        ->toContain('->recordUrl(')
        ->toContain('fi-projects-departments-table')
        ->toContain('con_actividades')
        ->toContain('->paginated([10, 25, 50])');

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/department-identity.blade.php'))
        ->toContain('fi-projects-department-identity')
        ->toContain('heroicon-o-building-office-2');

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/department-workload.blade.php'))
        ->toContain('fi-projects-department-workload')
        ->toContain('asignadas');

    expect(file_get_contents($helperPath))
        ->toContain('workloadMeta')
        ->toContain('resolveColor');
});
