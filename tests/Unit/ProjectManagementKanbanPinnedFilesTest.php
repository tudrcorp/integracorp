<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementKanbanFiles;

it('coloca los archivos favoritos al inicio conservando el orden relativo', function (): void {
    $files = [
        ['id' => 10, 'name' => 'A'],
        ['id' => 20, 'name' => 'B'],
        ['id' => 30, 'name' => 'C'],
        ['id' => 40, 'name' => 'D'],
    ];

    $sorted = ProjectManagementKanbanFiles::prioritizePinned($files, [30, 10]);

    expect(collect($sorted)->pluck('id')->all())->toBe([30, 10, 20, 40]);
});

it('no altera el orden cuando no hay favoritos', function (): void {
    $files = [
        ['id' => 1, 'name' => 'Uno'],
        ['id' => 2, 'name' => 'Dos'],
    ];

    expect(ProjectManagementKanbanFiles::prioritizePinned($files, []))->toBe($files);
});

it('kanban persiste y prioriza favoritos de archivos', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $pinPartialPath = dirname(__DIR__, 2).'/resources/views/filament/projects/partials/kanban-file-pin-button.blade.php';
    $filesHelperPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanFiles.php';

    expect(file_get_contents($pagePath))
        ->toContain('prioritizePinned')
        ->toContain('loadPinnedFileIdsFromSession')
        ->toContain('persistPinnedFileIdsToSession')
        ->toContain('public function mount(): void');

    expect(file_get_contents($filesHelperPath))
        ->toContain('public static function prioritizePinned');

    expect(file_get_contents($pinPartialPath))
        ->toContain('wire:model.live="pinnedFileIds"')
        ->toContain('kanban-pin-checkbox-')
        ->toContain('kanban-files-pin-btn--active');
});
