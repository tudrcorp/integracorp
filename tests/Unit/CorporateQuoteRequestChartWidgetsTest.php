<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestCreatorsChart;
use Filament\Widgets\ChartWidget;

it('define el widget de creadores de solicitudes dress taylor', function (): void {
    expect(class_exists(CorporateQuoteRequestCreatorsChart::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuoteRequestCreatorsChart::class, ChartWidget::class))->toBeTrue();
});

it('agrupa solicitudes por created_by con filtros de ano y mes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestCreatorsChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain("->groupBy('created_by')")
        ->toContain('public ?int $month = null')
        ->toContain("'0' => 'Todo el año'")
        ->toContain('isFullYearPeriod')
        ->toContain('getMonthlyBreakdownChartData')
        ->toContain('buildTopCreatorsYearChartData')
        ->toContain('indexAxis: \'y\'')
        ->toContain('->limit(15)');
});

it('define el widget de canal agente/agencia para solicitudes dress taylor', function (): void {
    expect(class_exists(CorporateQuoteRequestChannelChart::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuoteRequestChannelChart::class, ChartWidget::class))->toBeTrue();
});

it('incluye interaccion por clic y top 15 agentes o agencias en solicitudes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestChannelChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain('public function openMonthDetail')
        ->toContain('public function resetToMonthly')
        ->toContain('public function toggleDetailView')
        ->toContain('buildTopAgenciesDetailChart')
        ->toContain("component?.call('openMonthDetail', month)")
        ->toContain("->groupBy('agent_id')")
        ->toContain("->groupBy('code_agency')")
        ->toContain("'names' =>");
});

it('registra los widgets de solicitudes dress taylor en livewire', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.stats-overview-total-corporate-quote-request', StatsOverviewTotalCorporateQuoteRequest::class);")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.corporate-quote-request-creators-chart', CorporateQuoteRequestCreatorsChart::class);")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.corporate-quote-request-channel-chart', CorporateQuoteRequestChannelChart::class);");
});
