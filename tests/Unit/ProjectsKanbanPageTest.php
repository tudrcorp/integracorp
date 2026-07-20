<?php

declare(strict_types=1);

it('registra la pagina kanban en el panel projects', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';
    $filterComponentPath = dirname(__DIR__, 2).'/resources/views/components/projects/kanban-filter-field.blade.php';

    expect(file_exists($pagePath))->toBeTrue();
    expect(file_exists($viewPath))->toBeTrue();
    expect(file_exists($filterComponentPath))->toBeTrue();

    $pageContent = file_get_contents($pagePath);
    $viewContent = file_get_contents($viewPath);

    expect($pageContent)
        ->toContain('class Kanban extends Page')
        ->toContain("protected static ?string \$navigationLabel = 'Kanban';")
        ->toContain("protected string \$view = 'filament.projects.pages.kanban';")
        ->toContain('ActivityResource::getUrl(\'create\', panel: \'projects\')')
        ->toContain('Project::query()')
        ->toContain('->orderBy(\'name\')')
        ->toContain('getHasActiveFiltersProperty')
        ->toContain('addActivityNoteAction')
        ->toContain('uploadActivityDocumentAction')
        ->toContain('moveActivity')
        ->toContain('ProjectManagementKanbanActivityModalActions')
        ->toContain('getTimelinePayloadProperty')
        ->toContain("public string \$viewMode = 'board'")
        ->toContain("public string \$sprintFilter = 'active'");

    expect($viewContent)
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('wire:model.live="statusFilter"')
        ->toContain('wire:model.live="projectFilter"')
        ->toContain('wire:model.live="sprintFilter"')
        ->toContain('wire:model.live="sortBy"')
        ->toContain('kanban-filter-select')
        ->toContain('Todos los proyectos')
        ->toContain('x-projects.kanban-filter-field')
        ->toContain('ProjectManagementActivityTable::resolveColor')
        ->toContain('x-projects.kanban-project-affiliation')
        ->toContain('text-[10px] leading-4')
        ->toContain('dark:bg-gray-950')
        ->toContain('kanban-activity-card')
        ->toContain('kanban-column-list')
        ->toContain('--kanban-visible-cards: 4')
        ->not->toContain('max-h-[62vh]')
        ->toContain('x-projects.kanban-activity-assignees')
        ->toContain('x-projects.kanban-project-affiliation')
        ->toContain("getUrl('view', ['record' => \$activity], 'projects')")
        ->toContain('heroicon-m-eye')
        ->toContain("mountAction('addActivityNote'")
        ->toContain("mountAction('uploadActivityDocument'")
        ->toContain('heroicon-m-chat-bubble-left-ellipsis')
        ->toContain('heroicon-m-arrow-up-tray')
        ->toContain('filament-actions::modals')
        ->toContain("wire:click=\"setViewMode('timeline')\"")
        ->toContain('x-projects.kanban-timeline')
        ->toContain("wire:click=\"setViewMode('files')\"")
        ->toContain('components.projects.kanban-files');

    $affiliationPath = dirname(__DIR__, 2).'/resources/views/components/projects/kanban-project-affiliation.blade.php';
    expect(file_get_contents($affiliationPath))
        ->toContain('kanban-affiliation--hierarchy')
        ->toContain('kanban-affiliation__child-kicker')
        ->toContain('Subproyecto');
});

it('define modales de notas y documentos con contexto alineado al estatus de proyectos', function (): void {
    $actionsPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanActivityModalActions.php';
    $contextViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/actions/kanban-activity-modal-context.blade.php';
    $statusContextPath = dirname(__DIR__, 2).'/resources/views/filament/projects/actions/update-project-status-context.blade.php';

    expect(file_get_contents($actionsPath))
        ->toContain('makeAddNoteAction')
        ->toContain('makeUploadDocumentAction')
        ->toContain('modalWidth(Width::ThreeExtraLarge)')
        ->toContain('modalSubmitActionLabel')
        ->toContain('kanban-activity-modal-context')
        ->toContain('notesLogs()->create')
        ->toContain('documents()->create')
        ->toContain('activityViewBitacoraUrl')
        ->toContain('tryResolveActivity')
        ->toContain('activityHasNotes')
        ->toContain('addNoteModalFooterActions')
        ->toContain('viewActivityBitacora')
        ->toContain('ACTIVITY_INFOLIST_BITACORA_TAB_QUERY')
        ->toContain('rawurlencode');

    expect(file_get_contents($contextViewPath))
        ->toContain('Actividad seleccionada')
        ->toContain('Notas registradas')
        ->toContain('Última nota')
        ->toContain('Ver todas');

    expect(file_get_contents($statusContextPath))
        ->toContain('Proyecto seleccionado');
});
