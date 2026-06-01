<?php

declare(strict_types=1);

it('kanban expone vista lista con la tabla de actividades del recurso', function (): void {
    $kanbanPath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $kanbanViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';
    $activitiesTablePath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Tables/ActivitiesTable.php';
    $queryPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanActivitiesQuery.php';
    $dueColumnPath = dirname(__DIR__, 2).'/resources/views/filament/projects/tables/columns/activity-due.blade.php';
    $identityColumnPath = dirname(__DIR__, 2).'/resources/views/filament/projects/tables/columns/activity-identity.blade.php';

    expect(file_get_contents($kanbanPath))
        ->toContain('HasTable')
        ->toContain('InteractsWithTable')
        ->toContain('ActivitiesTable::configureForKanban')
        ->toContain('ProjectManagementKanbanActivitiesQuery')
        ->toContain('getKanbanActivitiesQuery')
        ->toContain('updatedSortBy')
        ->toContain("'list'");

    expect(file_get_contents($kanbanViewPath))
        ->toContain("setViewMode('list')")
        ->toContain('kanban-activities-table')
        ->toContain('getTable()->render()')
        ->toContain('$viewMode === \'list\'')
        ->not->toContain('kanban-activities-list')
        ->not->toContain('kanban-list-activity-card');

    expect(file_get_contents($activitiesTablePath))
        ->toContain('configureForKanban')
        ->toContain('activity_identity')
        ->toContain('activity-due')
        ->toContain('activity-assignment')
        ->toContain('->searchable(false)')
        ->toContain('kanbanActivities');

    expect(file_get_contents($queryPath))
        ->toContain('applyFilters')
        ->toContain('applySort')
        ->toContain('kanban_archived_at');

    expect(file_get_contents($dueColumnPath))
        ->toContain('linear-gradient(90deg, #22c55e')
        ->toContain('role="progressbar"');

    expect(file_get_contents($identityColumnPath))
        ->toContain('fi-projects-activity-identity')
        ->toContain('$record->title');
});
