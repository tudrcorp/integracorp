<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementGroupTable;

it('define tabla premium de equipos con columnas visuales y soporte de datos', function (): void {
    $basePath = dirname(__DIR__, 2);

    $tablePath = $basePath.'/app/Filament/Projects/Resources/ProjectManagement/Groups/Tables/GroupsTable.php';
    $supportPath = $basePath.'/app/Support/Filament/ProjectManagement/ProjectManagementGroupTable.php';

    expect(file_get_contents($tablePath))
        ->toContain('ViewColumn::make(\'group_identity\')')
        ->toContain('ViewColumn::make(\'team_members\')')
        ->toContain('ViewColumn::make(\'team_workload\')')
        ->toContain('->recordUrl(')
        ->toContain('executedActivities')
        ->toContain('->paginated([10, 25, 50])')
        ->toContain('TernaryFilter::make(\'con_integrantes\')')
        ->toContain('emptyStateIcon');

    expect(file_get_contents($supportPath))
        ->toContain('membersMeta')
        ->toContain('workloadMeta')
        ->toContain('resolveColor');

    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/group-identity.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/group-members.blade.php'))->toBeTrue();
    expect(file_exists($basePath.'/resources/views/filament/projects/tables/columns/group-workload.blade.php'))->toBeTrue();

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/group-identity.blade.php'))
        ->toContain('fi-projects-group-identity')
        ->toContain('line-clamp-2');

    expect(file_get_contents($basePath.'/resources/views/filament/projects/tables/columns/group-members.blade.php'))
        ->toContain('collaborator-avatar-stack');

    $group = new \App\Models\ProjectManagement\Group;
    $group->forceFill([
        'id' => 1,
        'name' => 'Equipo prueba',
        'executed_activities_count' => 0,
        'executed_activities_done_count' => 0,
        'executed_activities_open_count' => 0,
    ]);

    expect(ProjectManagementGroupTable::workloadMeta($group))
        ->toMatchArray([
            'percent' => null,
            'label' => 'Sin actividades asignadas',
            'tone' => 'muted',
            'done' => 0,
            'open' => 0,
            'total' => 0,
        ]);
});
