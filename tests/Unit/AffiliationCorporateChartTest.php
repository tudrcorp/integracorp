<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporateChart;

it('grafico de afiliaciones corporativas usa translatedFormat en lugar de translatedMonth', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporateChart.php');

    expect($source)
        ->toContain("->translatedFormat('F')")
        ->not->toContain('translatedMonth');
});

it('grafico de afiliaciones corporativas hace drill-down por año sin filtro select', function (): void {
    $ref = new ReflectionClass(AffiliationCorporateChart::class);
    $source = file_get_contents($ref->getFileName());

    expect($ref->getDefaultProperties()['view'] ?? null)
        ->toBe('filament.widgets.affiliation-corporate-chart');

    expect($source)
        ->toContain('openYearDetail')
        ->toContain('openMonthDetail')
        ->toContain('resetToYears')
        ->toContain('resetToMonths')
        ->toContain('selectedYear')
        ->not->toContain('$years[$year] = (string) $year');

    $widget = new AffiliationCorporateChart;
    $filters = (new ReflectionMethod(AffiliationCorporateChart::class, 'getFilters'))->invoke($widget);

    expect($filters)->toBeNull();

    $availableYears = (new ReflectionMethod(AffiliationCorporateChart::class, 'availableYears'))->invoke($widget);

    expect($availableYears)
        ->toBeArray()
        ->toHaveCount(5)
        ->toContain(now()->year);
});
