<?php

declare(strict_types=1);

it('actividad de proyecto soporta archivado en kanban', function (): void {
    $modelPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Activity.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_30_123533_add_kanban_archived_at_to_activities_table.php';

    expect(file_get_contents($modelPath))
        ->toContain('kanban_archived_at')
        ->toContain('isArchivedFromKanban');

    expect(file_get_contents($migrationPath))
        ->toContain('kanban_archived_at');
});

it('kanban filtra actividades archivadas segun visibilidad', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';

    expect(file_get_contents($pagePath))
        ->toContain('public string $archivedFilter = \'active\'')
        ->toContain('getArchivedFilterOptionsProperty')
        ->toContain('archived\' => \'Archivadas\'');

    expect(file_get_contents($viewPath))
        ->toContain('label="Visibilidad"')
        ->toContain('isArchivedFromKanban');
});
