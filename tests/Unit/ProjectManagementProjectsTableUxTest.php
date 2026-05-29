<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementProjectTable;

it('define meta de cronograma y estatus para tabla premium de proyectos', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/Tables/ProjectsTable.php';
    $supportPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementProjectTable.php';
    $identityViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/tables/columns/project-identity.blade.php';
    $timelineViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/tables/columns/project-timeline.blade.php';
    $statusModalViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/actions/update-project-status-context.blade.php';

    expect(file_exists($tablePath))->toBeTrue();
    expect(file_exists($supportPath))->toBeTrue();
    expect(file_exists($identityViewPath))->toBeTrue();
    expect(file_exists($timelineViewPath))->toBeTrue();

    expect(file_get_contents($timelineViewPath))
        ->toContain('linear-gradient(90deg, #22c55e')
        ->toContain('role="progressbar"');

    $tableContent = file_get_contents($tablePath);

    expect($tableContent)
        ->toContain('ViewColumn::make(\'project_identity\')')
        ->toContain('ViewColumn::make(\'timeline\')')
        ->toContain('TextColumn::make(\'delay_days\')')
        ->toContain('->withCount([\'subprojects\', \'activities\'])')
        ->toContain('->recordUrl(')
        ->toContain('Filter::make(\'vencidos\')')
        ->toContain('Action::make(\'update_status\')')
        ->toContain('ToggleButtons::make(\'status\')')
        ->toContain('->successNotification(')
        ->toContain('update-project-status-context')
        ->toContain('->paginated([10, 25, 50])');

    expect(file_exists($statusModalViewPath))->toBeTrue();

    expect(ProjectManagementProjectTable::statusMeta('active'))
        ->toBe(['label' => 'Activo', 'color' => 'success']);

    expect(ProjectManagementProjectTable::statusMeta('on_hold'))
        ->toBe(['label' => 'En espera', 'color' => 'warning']);

    expect(ProjectManagementProjectTable::statusOptions())
        ->toHaveKeys(['active', 'on_hold', 'completed']);
});
