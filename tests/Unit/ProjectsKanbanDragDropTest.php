<?php

declare(strict_types=1);

it('registra drag and drop en kanban con actualizacion de estatus', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';

    expect(file_get_contents($pagePath))
        ->toContain('public function moveActivity(int $activityId, string $status): void')
        ->toContain("->update(['status' => \$status])")
        ->toContain('skipRender()')
        ->toContain('Notification::make()');

    expect(file_get_contents($viewPath))
        ->toContain('data-kanban-column')
        ->toContain('data-kanban-status')
        ->toContain('data-activity-id')
        ->toContain('kanban-card-fallback')
        ->toContain('runPickupMotion')
        ->toContain('kanban-card-surface')
        ->toContain('startPickupTiltTracking');
});
