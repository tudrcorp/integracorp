<?php

declare(strict_types=1);

it('registra tabs de bitacora y documentos en el infolist de actividades', function (): void {
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Schemas/ActivityInfolist.php';
    $displayPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementActivityInfolistDisplay.php';
    $notesViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/activity-notes-bitacora.blade.php';
    $documentsViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/activity-documents-list.blade.php';
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/ActivityResource.php';
    $schemasPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementFilamentSchemas.php';
    $kanbanActionsPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanActivityModalActions.php';

    expect(file_get_contents($schemasPath))
        ->toContain('ACTIVITY_INFOLIST_BITACORA_TAB_QUERY')
        ->toContain('bitacora::tab');

    expect(file_get_contents($kanbanActionsPath))
        ->toContain('ACTIVITY_INFOLIST_BITACORA_TAB_QUERY')
        ->toContain('rawurlencode');

    $descriptionViewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/activity-description-highlight.blade.php';

    expect(file_get_contents($infolistPath))
        ->toContain("Tab::make('Bitácora')")
        ->toContain("Tab::make('Documentos')")
        ->toContain('ProjectManagementActivityInfolistDisplay::notesJournalPayload')
        ->toContain('ProjectManagementActivityInfolistDisplay::documentsPayload')
        ->toContain('ProjectManagementActivityInfolistDisplay::descriptionPayload')
        ->toContain('activity-notes-bitacora')
        ->toContain('activity-documents-list')
        ->toContain('activity-description-highlight')
        ->toContain("TextEntry::make('description_highlight')");

    expect(file_get_contents($displayPath))
        ->toContain('notesJournalPayload')
        ->toContain('documentsPayload')
        ->toContain('descriptionPayload')
        ->toContain('normalizeDescriptionText')
        ->toContain('formatFileSize');

    expect(file_get_contents($descriptionViewPath))
        ->toContain('Descripción de la actividad')
        ->toContain('Detalle resaltado')
        ->toContain('Sin descripción registrada')
        ->toContain('activity-description-highlight')
        ->toContain('text-justify')
        ->toContain('text-align: justify');

    expect(file_get_contents($notesViewPath))
        ->toContain('Bitácora de actividad')
        ->toContain('filteredNotes()');

    expect(file_get_contents($documentsViewPath))
        ->toContain('Descargar')
        ->toContain('paginatedDocuments()');

    expect(file_get_contents($resourcePath))
        ->toContain('getRecordRouteBindingEloquentQuery')
        ->toContain('notesLogs')
        ->toContain('documents');
});
