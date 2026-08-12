<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\CorporateQuotesByAgencyTable;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\CorporateQuotesByAgentTable;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewTotalCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\TotalCorporateQuoteChart;
use App\Support\Filament\CorporateQuotesRankingTableUi;
use Filament\Widgets\TableWidget;

it('registra los widgets de resumen, grafico y ranking en el listado de cotizaciones corporativas', function (): void {
    $page = new class extends ListCorporateQuotes
    {
        public function exposedHeaderWidgets(): array
        {
            return $this->getHeaderWidgets();
        }

        public function exposedHeaderWidgetsColumns(): int|array
        {
            return $this->getHeaderWidgetsColumns();
        }
    };

    expect($page->exposedHeaderWidgets())->toBe([
        StatsOverviewTotalCorporateQuote::class,
        StatsOverviewCorporateQuote::class,
        TotalCorporateQuoteChart::class,
        CorporateQuotesByAgencyTable::class,
        CorporateQuotesByAgentTable::class,
    ]);

    expect($page->exposedHeaderWidgetsColumns())->toBe([
        'default' => 1,
        'lg' => 2,
    ]);
});

it('define el widget de cotizaciones por agencia con columnas y filtros de periodo', function (): void {
    expect(class_exists(CorporateQuotesByAgencyTable::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuotesByAgencyTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/CorporateQuotesByAgencyTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('CorporateQuotesRankingTableUi::apply')
        ->toContain("variant: 'agency'")
        ->toContain("nameAttribute: 'name_corporative'")
        ->toContain('CorporateQuotesRankingQuery::agencies')
        ->toContain('resolvedRankingFilterYear')
        ->toContain('resolvedRankingFilterMonth')
        ->toContain("Action::make('filterAgents')")
        ->toContain("->label('Detalles')")
        ->toContain('syncPeriodToAgentTable')
        ->toContain('corporate-quotes-period-changed');
});

it('define el widget de cotizaciones por agente con columnas requeridas', function (): void {
    expect(class_exists(CorporateQuotesByAgentTable::class))->toBeTrue()
        ->and(is_subclass_of(CorporateQuotesByAgentTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/CorporateQuotesByAgentTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('CorporateQuotesRankingTableUi::apply')
        ->toContain("variant: 'agent'")
        ->toContain("nameAttribute: 'name'")
        ->toContain('CorporateQuotesRankingQuery::agents')
        ->toContain('flushCachedTableRecords')
        ->toContain('fn (): Builder => $this->agentQuotesQuery()')
        ->toContain("Action::make('viewQuotes')")
        ->toContain("->label('Ver cotizaciones')")
        ->toContain("#[On('corporate-quotes-period-changed')]")
        ->toContain('applyPeriodFilter');
});

it('coloca las tablas de ranking lado a lado', function (): void {
    foreach ([CorporateQuotesByAgencyTable::class, CorporateQuotesByAgentTable::class] as $widgetClass) {
        $defaults = (new ReflectionClass($widgetClass))->getDefaultProperties();

        expect($defaults['columnSpan'])->toBe(1);
    }
});

it('filtra agentes al seleccionar una agencia y cotizaciones al seleccionar un agente', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Pages/ListCorporateQuotes.php');
    $agencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/CorporateQuotesByAgencyTable.php');
    $agentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/CorporateQuotesByAgentTable.php');
    $query = file_get_contents(dirname(__DIR__, 2).'/app/Support/CorporateQuotes/CorporateQuotesRankingQuery.php');
    $quotesTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php');

    expect($listPage)
        ->toContain('#[On(\'corporate-quotes-filter-by-agent\')]')
        ->toContain('filterQuotesByAgent')
        ->toContain('corporate-quotes-main-table');

    expect($agencyWidget)
        ->toContain('selectAgency')
        ->toContain('->to(CorporateQuotesByAgentTable::class)')
        ->toContain('corporate-quotes-agent-filter-start');

    expect($agentWidget)
        ->toContain('filterAgentsByAgency')
        ->toContain('viewAgentQuotes')
        ->toContain('->to(ListCorporateQuotes::class)');

    expect($query)
        ->toContain('CorporateQuote::query()')
        ->toContain("DB::raw('COUNT(*) as total_quotes')")
        ->toContain('applyPeriod');

    expect($quotesTable)
        ->toContain("SelectFilter::make('agent_id')")
        ->toContain("'id' => 'corporate-quotes-main-table'");
});

it('expone headings de ranking para cotizaciones corporativas', function (): void {
    expect(CorporateQuotesRankingTableUi::heading('agency'))->toBe('Cotizaciones por agencia')
        ->and(CorporateQuotesRankingTableUi::heading('agent'))->toBe('Cotizaciones por agente');
});

it('define el query de ranking corporativo con conteo y periodo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/CorporateQuotes/CorporateQuotesRankingQuery.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('class CorporateQuotesRankingQuery')
        ->toContain('public static function agencies')
        ->toContain('public static function agents')
        ->toContain("DB::raw('COUNT(*) as total_quotes')")
        ->toContain('protected static function applyPeriod')
        ->toContain("->whereYear('created_at', \$year)")
        ->toContain("->whereMonth('created_at', \$month)");
});

it('registra los widgets de ranking de cotizaciones corporativas en Livewire', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($provider)->not->toBeFalse()
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quotes.widgets.corporate-quotes-by-agency-table', CorporateQuotesByAgencyTable::class)")
        ->toContain("Livewire::component('app.filament.business.resources.corporate-quotes.widgets.corporate-quotes-by-agent-table', CorporateQuotesByAgentTable::class)");
});
