<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgencyTable;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgentTable;
use App\Support\Filament\CorporateQuoteRequestsRankingTableUi;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\TableWidget;

it('define el widget de canal agente/agencia para solicitudes dress taylor', function (): void {
    expect(class_exists(CorporateQuoteRequestChannelChart::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuoteRequestChannelChart::class, ChartWidget::class))->toBeTrue();
});

it('incluye historico mensual con hover tdec/tdev y sin detalle al hacer clic', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestChannelChart.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain('->perMonth()')
        ->toContain('monthlyCompanyCounts')
        ->toContain("'tdecCounts'")
        ->toContain("'tdevCounts'")
        ->toContain("lines.push(' TDEC: '")
        ->toContain("lines.push(' TDEV: '")
        ->toContain('Histórico mensual de solicitudes Dress Taylor')
        ->not->toContain('openMonthDetail')
        ->not->toContain('resetToMonthly')
        ->not->toContain('toggleDetailView')
        ->not->toContain('buildTopAgenciesDetailChart')
        ->not->toContain('buildTopAgentsDetailChart')
        ->not->toContain('selectedMonth')
        ->not->toContain('Haz clic');
});

it('define el widget de solicitudes por agencia con columnas y filtros de periodo', function (): void {
    expect(class_exists(CorporateQuoteRequestsByAgencyTable::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuoteRequestsByAgencyTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestsByAgencyTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('CorporateQuoteRequestsRankingTableUi::apply')
        ->toContain("variant: 'agency'")
        ->toContain("nameAttribute: 'name_corporative'")
        ->toContain('CorporateQuoteRequestsRankingQuery::agencies')
        ->toContain('resolvedRankingFilterYear')
        ->toContain('resolvedRankingFilterMonth')
        ->toContain("Action::make('filterAgents')")
        ->toContain("->label('Detalles')")
        ->toContain('syncPeriodToAgentTable')
        ->toContain('corporate-quote-requests-period-changed');
});

it('define el widget de solicitudes por agente con columnas requeridas', function (): void {
    expect(class_exists(CorporateQuoteRequestsByAgentTable::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuoteRequestsByAgentTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestsByAgentTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('CorporateQuoteRequestsRankingTableUi::apply')
        ->toContain("variant: 'agent'")
        ->toContain("nameAttribute: 'name'")
        ->toContain('CorporateQuoteRequestsRankingQuery::agents')
        ->toContain('flushCachedTableRecords')
        ->toContain('fn (): Builder => $this->agentRequestsQuery()')
        ->toContain("Action::make('viewRequests')")
        ->toContain("->label('Ver solicitudes')")
        ->toContain("#[On('corporate-quote-requests-period-changed')]")
        ->toContain('applyPeriodFilter');
});

it('coloca las tablas de ranking lado a lado', function (): void {
    foreach ([CorporateQuoteRequestsByAgencyTable::class, CorporateQuoteRequestsByAgentTable::class] as $widgetClass) {
        $defaults = (new ReflectionClass($widgetClass))->getDefaultProperties();

        expect($defaults['columnSpan'])->toBe(1);
    }
});

it('filtra agentes al seleccionar una agencia y solicitudes al seleccionar un agente', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Pages/ListCorporateQuoteRequests.php');
    $agencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestsByAgencyTable.php');
    $agentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Widgets/CorporateQuoteRequestsByAgentTable.php');
    $query = file_get_contents(dirname(__DIR__, 2).'/app/Support/CorporateQuoteRequests/CorporateQuoteRequestsRankingQuery.php');
    $requestsTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Tables/CorporateQuoteRequestsTable.php');

    expect($listPage)
        ->toContain('#[On(\'corporate-quote-requests-filter-by-agent\')]')
        ->toContain('filterRequestsByAgent')
        ->toContain('corporate-quote-requests-main-table');

    expect($agencyWidget)
        ->toContain('selectAgency')
        ->toContain('->to(CorporateQuoteRequestsByAgentTable::class)')
        ->toContain('corporate-quote-requests-agent-filter-start');

    expect($agentWidget)
        ->toContain('filterAgentsByAgency')
        ->toContain('viewAgentRequests')
        ->toContain('->to(ListCorporateQuoteRequests::class)');

    expect($query)
        ->toContain('CorporateQuoteRequest::query()')
        ->toContain("DB::raw('COUNT(*) as total_requests')")
        ->toContain('applyPeriod');

    expect($requestsTable)
        ->toContain("SelectFilter::make('agent_id')")
        ->toContain("'id' => 'corporate-quote-requests-main-table'");
});

it('expone headings de ranking para solicitudes dress taylor', function (): void {
    expect(CorporateQuoteRequestsRankingTableUi::heading('agency'))->toBe('Solicitudes por agencia')
        ->and(CorporateQuoteRequestsRankingTableUi::heading('agent'))->toBe('Solicitudes por agente');
});

it('define el query de ranking de solicitudes con conteo y periodo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/CorporateQuoteRequests/CorporateQuoteRequestsRankingQuery.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('class CorporateQuoteRequestsRankingQuery')
        ->toContain('public static function agencies')
        ->toContain('public static function agents')
        ->toContain("DB::raw('COUNT(*) as total_requests')")
        ->toContain('protected static function applyPeriod')
        ->toContain("->whereYear('created_at', \$year)")
        ->toContain("->whereMonth('created_at', \$month)");
});

it('registra los widgets de solicitudes dress taylor en livewire', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.stats-overview-total-corporate-quote-request', StatsOverviewTotalCorporateQuoteRequest::class);")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.corporate-quote-requests-by-agency-table', CorporateQuoteRequestsByAgencyTable::class)")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.corporate-quote-requests-by-agent-table', CorporateQuoteRequestsByAgentTable::class)")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quote-requests.widgets.corporate-quote-request-channel-chart', CorporateQuoteRequestChannelChart::class);")
        ->not->toContain('corporate-quote-request-creators-chart');
});
