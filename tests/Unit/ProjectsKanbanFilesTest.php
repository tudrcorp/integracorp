<?php

declare(strict_types=1);

it('construye payload de archivos del kanban con categorias y metadatos', function (): void {
    $filesPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanFiles.php';
    $componentPath = dirname(__DIR__, 2).'/resources/views/components/projects/kanban-files.blade.php';

    expect(file_exists($filesPath))->toBeTrue();
    expect(file_exists($componentPath))->toBeTrue();

    expect(file_get_contents($filesPath))
        ->toContain('final class ProjectManagementKanbanFiles')
        ->toContain('public static function build(')
        ->toContain('Document::query()')
        ->toContain("'category' => \$fileCategory")
        ->toContain('ProjectManagementActivityAssignmentDisplay::for')
        ->toContain('formatFileSize')
        ->toContain('resolveCategory');

    expect(file_get_contents($componentPath))
        ->toContain('kanban-files')
        ->toContain('kanban-files-tabs__track')
        ->toContain('kanban-files-tab__count')
        ->toContain('kanban-files-tab__icon')
        ->toContain('setFilesCategory')
        ->toContain('filesSort')
        ->toContain('setFilesLayout')
        ->toContain('filament.projects.partials.kanban-file-pin-button')
        ->toContain('kanban-files-pinned-hint')
        ->toContain('kanban-files-grid')
        ->toContain('kanban-files-list')
        ->toContain('collaborator-avatar-stack')
        ->toContain('kanban-files-card__activity')
        ->toContain('activity_title')
        ->toContain('activity_view_url')
        ->toContain('kanban-files-pin-btn--active')
        ->toContain(':is(.dark, .dark *) .kanban-files');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanFiles.php'))
        ->toContain('prioritizePinned');
});

it('expone vista files en kanban con cambio de modo y favoritos', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';

    expect(file_get_contents($pagePath))
        ->toContain('getFilesPayloadProperty')
        ->toContain('ProjectManagementKanbanFiles::build')
        ->toContain("public string \$filesCategory = 'all'")
        ->toContain("public string \$filesLayout = 'grid'")
        ->toContain('updatedPinnedFileIds')
        ->toContain('getNormalizedPinnedFileIdsProperty')
        ->toContain("in_array(\$viewMode, ['board', 'timeline', 'files', 'list'], true)");

    expect(file_get_contents($viewPath))
        ->toContain("wire:click=\"setViewMode('files')\"")
        ->toContain("include('components.projects.kanban-files'")
        ->toContain("'pinnedFileIds' => \$this->pinnedFileIds");
});
