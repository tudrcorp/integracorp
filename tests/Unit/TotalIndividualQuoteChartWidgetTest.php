<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Widgets\TotalIndividualQuoteChart;
use Filament\Widgets\ChartWidget;

it('define el widget de histórico mensual y top 15 por código', function (): void {
    expect(class_exists(TotalIndividualQuoteChart::class))->toBeTrue()
        ->and(is_subclass_of(TotalIndividualQuoteChart::class, ChartWidget::class))->toBeTrue();
});

it('incluye interacción por clic y tooltip con nombre + cantidad', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/TotalIndividualQuoteChart.php';
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
        ->toContain("->whereNotNull('agent_id')")
        ->toContain("->orderByDesc('last_quote_at')")
        ->toContain("->groupBy('code_agency')")
        ->toContain("'names' =>")
        ->toContain("'#38bdf8'")
        ->toContain("'borderSkipped' => false")
        ->toContain("return ' Cotizaciones: ' + context.raw;");
});
