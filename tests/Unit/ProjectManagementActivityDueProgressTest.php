<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;
use Illuminate\Support\Carbon;

it('calcula porcentaje preciso del plazo segun fecha limite', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00'));

    $start = Carbon::parse('2026-05-01 09:00:00');
    $due = Carbon::parse('2026-05-21 18:00:00');

    expect(ProjectManagementActivityTable::calculateDueProgressPercent($start, $due))->toBeGreaterThan(60)
        ->toBeLessThan(80);

    expect(ProjectManagementActivityTable::calculateDueProgressPercent($start, $due, Carbon::parse('2026-04-30 18:00:00')))->toBe(0);

    expect(ProjectManagementActivityTable::calculateDueProgressPercent($start, $due, Carbon::parse('2026-05-22 10:00:00')))->toBe(100);

    $window = ProjectManagementActivityTable::dueWindowMeta($start, $due);

    expect($window)
        ->toHaveKeys(['total_days', 'elapsed_days', 'remaining_days', 'progress_detail'])
        ->and($window['total_days'])->toBe(20)
        ->and($window['remaining_days'])->toBe(6);

    Carbon::setTestNow();
});

it('documenta barra de plazo con porcentaje y detalle en vista de actividades', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/components/projects/activity-due-progress.blade.php';

    expect(file_get_contents($viewPath))
        ->toContain('progress_detail')
        ->toContain('aria-valuetext')
        ->toContain('Consumo del plazo')
        ->toContain('min-w-[18rem]');
});

it('define resumen de ejecucion kanban para actividades finalizadas', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementActivityTable.php';

    expect(file_get_contents($tablePath))
        ->toContain('kanbanDoneExecutionSummary')
        ->toContain("if (\$activity->status !== 'done')")
        ->toContain('started_label')
        ->toContain('finished_label')
        ->toContain('optimal_label')
        ->toContain('elapsed_label')
        ->toContain('within_range')
        ->toContain('kanbanDaysLabel');
});

it('kanban oculta acciones y muestra resumen en actividades finalizadas', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';

    expect(file_get_contents($viewPath))
        ->toContain("if (\$activity->status === 'done')")
        ->toContain('@else')
        ->toContain('kanbanDoneExecutionSummary')
        ->toContain('within_range')
        ->toContain('Plazo óptimo')
        ->toContain('Ejecución')
        ->toContain('started_label')
        ->toContain('archiveActivityFromKanban')
        ->toContain('kanban-priority-badge--high')
        ->toContain('heroicon-m-archive-box')
        ->toContain('text-red-700')
        ->toContain('x-projects.kanban-activity-assignees')
        ->toContain('mountAction(\'addActivityNote\'');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php'))
        ->not->toContain('skipRender()');
});
