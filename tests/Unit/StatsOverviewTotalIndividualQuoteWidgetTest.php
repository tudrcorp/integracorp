<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewTotalIndividualQuote;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de total de cotizaciones individuales', function (): void {
    expect(class_exists(StatsOverviewTotalIndividualQuote::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewTotalIndividualQuote::class, StatsOverviewWidget::class))->toBeTrue();
});

it('envuelve las stats en Section con filtros año/mes en afterHeader', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/StatsOverviewTotalIndividualQuote.php';
    $code = file_get_contents($path);

    expect($code)
        ->not->toBeFalse()
        ->toContain('public array $statsFilters = [];')
        ->toContain("View::make('filament.widgets.stats-overview-filters')")
        ->toContain('updatedStatsFiltersYear')
        ->toContain('updatedStatsFiltersMonth');
});
