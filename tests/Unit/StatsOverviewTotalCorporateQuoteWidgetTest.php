<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewTotalCorporateQuote;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de total de cotizaciones corporativas', function (): void {
    expect(class_exists(StatsOverviewTotalCorporateQuote::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewTotalCorporateQuote::class, StatsOverviewWidget::class))->toBeTrue();
});

it('envuelve las stats corporativas en Section con filtros ano/mes en afterHeader', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/StatsOverviewTotalCorporateQuote.php';
    $code = file_get_contents($path);

    expect($code)
        ->not->toBeFalse()
        ->toContain('public array $statsFilters = [];')
        ->toContain("View::make('filament.widgets.stats-overview-filters')")
        ->toContain('updatedStatsFiltersYear')
        ->toContain('updatedStatsFiltersMonth');
});
