<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Widgets\TotalCorporateQuoteChart;
use Filament\Widgets\ChartWidget;

it('define el widget de historico mensual corporativo y top 15 por codigo', function (): void {
    expect(class_exists(TotalCorporateQuoteChart::class))->toBeTrue()
        ->and(is_subclass_of(TotalCorporateQuoteChart::class, ChartWidget::class))->toBeTrue();
});

it('incluye interaccion por clic y tooltip con nombre + cantidad en corporativas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/TotalCorporateQuoteChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain('public function openMonthDetail')
        ->toContain('public function resetToMonthly')
        ->toContain('public function toggleDetailView')
        ->toContain('buildTopAgenciesDetailChart')
        ->toContain("component?.call('openMonthDetail', month)")
        ->toContain('->limit(15)')
        ->toContain("->groupBy('agent_id')")
        ->toContain("->orderByDesc('last_quote_at')")
        ->toContain("->groupBy('code_agency')")
        ->toContain("'names' =>")
        ->toContain("'#38bdf8'")
        ->toContain("'borderSkipped' => false")
        ->toContain("return ' Cotizaciones: ' + context.raw;");
});
