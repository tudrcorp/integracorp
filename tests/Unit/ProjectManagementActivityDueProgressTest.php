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
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/tables/columns/activity-due.blade.php';

    expect(file_get_contents($viewPath))
        ->toContain('progress_detail')
        ->toContain('aria-valuetext')
        ->toContain('Consumo del plazo')
        ->toContain('min-w-[18rem]');
});
