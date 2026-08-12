<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Widgets\TotalCorporateQuoteChart;
use Filament\Widgets\ChartWidget;

it('define el widget de historico mensual corporativo', function (): void {
    expect(class_exists(TotalCorporateQuoteChart::class))->toBeTrue()
        ->and(is_subclass_of(TotalCorporateQuoteChart::class, ChartWidget::class))->toBeTrue();
});

it('muestra solo el historico mensual sin detalle al hacer clic', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/TotalCorporateQuoteChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain('Histórico mensual de cotizaciones corporativas.')
        ->toContain('->perMonth()')
        ->toContain("'#38bdf8'")
        ->toContain("'borderSkipped' => false")
        ->toContain("return ' Cotizaciones: ' + context.raw;")
        ->not->toContain('openMonthDetail')
        ->not->toContain('resetToMonthly')
        ->not->toContain('toggleDetailView')
        ->not->toContain('buildTopAgenciesDetailChart')
        ->not->toContain('buildTopAgentsDetailChart')
        ->not->toContain('selectedMonth');
});
