<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverviewPlan;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de análisis de afiliaciones por plan', function (): void {
    expect(class_exists(StatsOverviewPlan::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewPlan::class, StatsOverviewWidget::class))->toBeTrue();
});

it('incluye filtros de año y mes con opción todo el año y sin efecto hover', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/StatsOverviewPlan.php';
    $code = file_get_contents($path);

    expect($code)
        ->not->toBeFalse()
        ->toContain('Todo el año')
        ->toContain("View::make('filament.widgets.stats-overview-filters')")
        ->toContain('statsFilters')
        ->toContain('getYearSelectOptions')
        ->toContain('getMonthSelectOptions')
        ->toContain('whereBetween')
        ->not->toContain('@mouseenter')
        ->not->toContain('@mouseleave')
        ->not->toContain('hover:scale-');
});
