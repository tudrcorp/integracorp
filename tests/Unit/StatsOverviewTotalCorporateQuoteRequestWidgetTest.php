<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\StatsOverviewTotalCorporateQuoteRequest;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de total de solicitudes dress taylor', function (): void {
    expect(class_exists(StatsOverviewTotalCorporateQuoteRequest::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewTotalCorporateQuoteRequest::class, StatsOverviewWidget::class))->toBeTrue();
});

it('envuelve las stats de solicitudes en Section con filtros ano/mes en afterHeader', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/StatsOverviewTotalCorporateQuoteRequest.php';
    $code = file_get_contents($path);

    expect($code)
        ->not->toBeFalse()
        ->toContain('public array $statsFilters = [];')
        ->toContain("View::make('filament.widgets.stats-overview-filters')")
        ->toContain('updatedStatsFiltersYear')
        ->toContain('updatedStatsFiltersMonth')
        ->toContain('CorporateQuoteRequest::query()');
});
