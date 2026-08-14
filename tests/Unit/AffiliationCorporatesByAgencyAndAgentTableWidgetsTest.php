<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatesByAgencyTable;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatesByAgentTable;
use App\Support\Filament\AffiliationCorporatesRankingTableUi;
use Filament\Widgets\TableWidget;

it('registra los widgets de tabla por agencia y agente debajo de los gráficos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ListAffiliationCorporates.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->and($code)->toContain('AffiliationCorporateChart::class')
        ->and($code)->toContain('AffiliationCorporatesByAgencyTable::class')
        ->and($code)->toContain('AffiliationCorporatesByAgentTable::class');

    $chartPos = strpos($code, 'AffiliationCorporateChart::class');
    $agencyPos = strpos($code, 'AffiliationCorporatesByAgencyTable::class');
    $agentPos = strpos($code, 'AffiliationCorporatesByAgentTable::class');

    expect($chartPos)->toBeInt()->toBeLessThan($agencyPos)
        ->and($agencyPos)->toBeLessThan($agentPos);
});

it('define el widget de afiliaciones por agencia con columnas requeridas', function (): void {
    expect(class_exists(AffiliationCorporatesByAgencyTable::class))->toBeTrue()
        ->and(is_subclass_of(AffiliationCorporatesByAgencyTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporatesByAgencyTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('AffiliationCorporatesRankingTableUi::apply')
        ->toContain("variant: 'agency'")
        ->toContain("nameAttribute: 'name_corporative'")
        ->toContain('AffiliationCorporatesRankingQuery::agencies')
        ->toContain('resolvedRankingFilterYear')
        ->toContain('resolvedRankingFilterMonth')
        ->toContain("Action::make('filterAgents')")
        ->toContain("->label('Detalles')")
        ->toContain('syncPeriodToAgentTable')
        ->toContain('affiliation-corporates-period-changed');
});

it('define el widget de afiliaciones por agente con columnas requeridas', function (): void {
    expect(class_exists(AffiliationCorporatesByAgentTable::class))->toBeTrue()
        ->and(is_subclass_of(AffiliationCorporatesByAgentTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporatesByAgentTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('AffiliationCorporatesRankingTableUi::apply')
        ->toContain("variant: 'agent'")
        ->toContain("nameAttribute: 'name'")
        ->toContain('AffiliationCorporatesRankingQuery::agents')
        ->toContain('flushCachedTableRecords')
        ->toContain('fn (): Builder => $this->agentAffiliationsQuery()')
        ->toContain("Action::make('viewAffiliations')")
        ->toContain("->label('Ver afiliaciones')")
        ->toContain('#[On(\'affiliation-corporates-period-changed\')]')
        ->toContain('applyPeriodFilter');
});

it('coloca las tablas lado a lado en la misma fila', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ListAffiliationCorporates.php';
    $listCode = file_get_contents($listPath);

    expect($listCode)->not->toBeFalse()
        ->toContain('getHeaderWidgetsColumns')
        ->toContain("'lg' => 2");

    foreach ([AffiliationCorporatesByAgencyTable::class, AffiliationCorporatesByAgentTable::class] as $widgetClass) {
        $reflection = new ReflectionClass($widgetClass);
        $defaults = $reflection->getDefaultProperties();

        expect($defaults['columnSpan'])->toBe(1);
    }
});

it('coloca los gráficos de resumen y por ubicación lado a lado', function (): void {
    $chart = new ReflectionClass(\App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporateChart::class);
    $porEstado = new ReflectionClass(\App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatePorEstadoChart::class);

    expect($chart->getDefaultProperties()['columnSpan'] ?? null)->toBe(1)
        ->and($porEstado->getDefaultProperties()['columnSpan'] ?? null)->toBe(1);
});

it('filtra agentes al seleccionar una agencia y afiliaciones al seleccionar un agente', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ListAffiliationCorporates.php');
    $agencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporatesByAgencyTable.php');
    $agentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporatesByAgentTable.php');
    $query = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/AffiliationCorporatesRankingQuery.php');
    $affiliationsTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($listPage)->not->toContain('selectAgencyForAgentFilter')
        ->not->toContain('filteredAgencyCode')
        ->not->toContain('getWidgetData');

    expect($agencyWidget)->toContain('selectAgency')
        ->toContain('->to(AffiliationCorporatesByAgentTable::class)')
        ->toContain('affiliation-corporates-agent-filter-start')
        ->toContain('getTableRecordKey($record) === (string) $key')
        ->toContain('->recordActions([')
        ->not->toContain('#[Reactive]');

    expect($agentWidget)->toContain('filterAgentsByAgency')
        ->toContain('viewAgentAffiliations')
        ->toContain('->to(ListAffiliationCorporates::class)')
        ->toContain('affiliation-corporates-filter-by-agent')
        ->toContain('#[On(\'affiliation-corporates-agency-selected\')]')
        ->toContain('AffiliationCorporatesRankingQuery::agents')
        ->toContain('Ver todos los agentes')
        ->not->toContain('#[Reactive]');

    expect($query)->toContain('joinSub')
        ->toContain("->where('code_agency', \$agencyCode)");

    expect($listPage)->toContain('filterAffiliationsByAgent')
        ->toContain('#[On(\'affiliation-corporates-filter-by-agent\')]')
        ->toContain('affiliation-corporates-main-table')
        ->toContain('scrollIntoView');

    expect($affiliationsTable)->toContain("SelectFilter::make('agent_id')")
        ->toContain("'id' => 'affiliation-corporates-main-table'");
});

it('aplica UI de ranking reutilizando estilos iOS compactos', function (): void {
    $root = dirname(__DIR__, 2);

    $ui = file_get_contents($root.'/app/Support/Filament/AffiliationCorporatesRankingTableUi.php');
    $widgetView = file_get_contents($root.'/resources/views/filament/widgets/affiliation-corporates-ranking-table-widget.blade.php');

    expect($ui)->toContain('->rowIndex()')
        ->toContain('individual-quotes-ranking-table-ios')
        ->toContain("TextColumn::make('total_affiliations')")
        ->toContain('mb_strtoupper($state')
        ->toContain('->striped()')
        ->toContain('->searchable(false)')
        ->toContain('->defaultPaginationPageOption(8)')
        ->toContain('->paginated([8, 16, 25, 50])')
        ->not->toContain('->searchPlaceholder(');

    expect($widgetView)->toContain('$widgetClass')
        ->toContain('getRankingTableVariant')
        ->toContain('iq-ranking-filter-overlay')
        ->toContain('affiliation-corporates-agent-filter-start')
        ->toContain('Preparando filtrado')
        ->toContain('ac-ranking-agency-header')
        ->toContain('ac-ranking-period-filters')
        ->toContain('getRankingYearFilterOptions')
        ->toContain('getRankingMonthFilterOptions')
        ->toContain('wire:model.live="filterYear"')
        ->toContain('wire:model.live="filterMonth"');

    expect(AffiliationCorporatesRankingTableUi::tableClass('agency'))
        ->toBe('individual-quotes-ranking-table-ios individual-quotes-ranking-table-ios--agency');
});

it('expone opciones de filtro con etiquetas Año y Mes (Todos)', function (): void {
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/Concerns/InteractsWithAffiliationCorporatesRankingTable.php');

    expect($trait)->not->toBeFalse()
        ->toContain("'Año '")
        ->toContain("'Mes (Todos)'")
        ->toContain('getRankingYearFilterOptions')
        ->toContain('getRankingMonthFilterOptions')
        ->toContain('resolvedRankingFilterYear')
        ->toContain('resolvedRankingFilterMonth');
});

it('registra los widgets de ranking en Livewire para evitar ComponentNotFoundException', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($provider)->not->toBeFalse()
        ->toContain('AffiliationCorporatesByAgencyTable::class')
        ->toContain('AffiliationCorporatesByAgentTable::class')
        ->toContain('affiliation-corporates.widgets.affiliation-corporates-by-agency-table')
        ->toContain('affiliation-corporates.widgets.affiliation-corporates-by-agent-table');
});

it('construye queries de ranking optimizadas con subconsultas', function (): void {
    $queryClass = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/AffiliationCorporatesRankingQuery.php');

    expect($queryClass)->toContain('joinSub')
        ->toContain('public static function agencies')
        ->toContain('public static function agents')
        ->toContain("->where('code_agency', \$agencyCode)")
        ->toContain('groupBy(\'code_agency\')')
        ->toContain('groupBy(\'agent_id\')')
        ->toContain('applyPeriod')
        ->toContain("whereYear('created_at', \$year)")
        ->toContain("whereMonth('created_at', \$month)");
});
