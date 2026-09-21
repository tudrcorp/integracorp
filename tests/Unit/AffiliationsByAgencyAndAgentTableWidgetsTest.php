<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationsByAgencyTable;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationsByAgentTable;
use Filament\Widgets\TableWidget;

it('registra los widgets de ranking debajo de los graficos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ListAffiliations.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->and($code)->toContain('AffiliationSupplierChart::class')
        ->and($code)->toContain('AffiliationsByAgencyTable::class')
        ->and($code)->toContain('AffiliationsByAgentTable::class');

    $chartPos = strpos($code, 'AffiliationSupplierChart::class');
    $agencyPos = strpos($code, 'AffiliationsByAgencyTable::class');
    $agentPos = strpos($code, 'AffiliationsByAgentTable::class');

    expect($chartPos)->toBeInt()->toBeLessThan($agencyPos)
        ->and($agencyPos)->toBeLessThan($agentPos);
});

it('define el widget de afiliaciones individuales por agencia con periodo y sin agente', function (): void {
    expect(class_exists(AffiliationsByAgencyTable::class))->toBeTrue()
        ->and(is_subclass_of(AffiliationsByAgencyTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationsByAgencyTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('AffiliationCorporatesRankingTableUi::apply')
        ->toContain("variant: 'agency'")
        ->toContain("nameAttribute: 'name_corporative'")
        ->toContain('AffiliationsRankingQuery::agencies')
        ->toContain('resolvedRankingFilterYear')
        ->toContain('resolvedRankingFilterMonth')
        ->toContain("Action::make('filterAgents')")
        ->toContain("->label('Detalles')")
        ->toContain("Action::make('viewAffiliationsWithoutAgent')")
        ->toContain("->label('Ver afiliaciones sin agente')")
        ->toContain('syncPeriodToAgentTable')
        ->toContain('affiliations-period-changed');
});

it('define el widget de afiliaciones individuales por agente', function (): void {
    expect(class_exists(AffiliationsByAgentTable::class))->toBeTrue()
        ->and(is_subclass_of(AffiliationsByAgentTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationsByAgentTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('AffiliationCorporatesRankingTableUi::apply')
        ->toContain("variant: 'agent'")
        ->toContain("nameAttribute: 'name'")
        ->toContain('AffiliationsRankingQuery::agents')
        ->toContain('flushCachedTableRecords')
        ->toContain('fn (): Builder => $this->agentAffiliationsQuery()')
        ->toContain("Action::make('viewAffiliations')")
        ->toContain("->label('Ver afiliaciones')")
        ->toContain('#[On(\'affiliations-period-changed\')]')
        ->toContain('applyPeriodFilter');
});

it('coloca las tablas de ranking lado a lado', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ListAffiliations.php';
    $listCode = file_get_contents($listPath);

    expect($listCode)->not->toBeFalse()
        ->toContain('getHeaderWidgetsColumns')
        ->toContain("'lg' => 2");

    foreach ([AffiliationsByAgencyTable::class, AffiliationsByAgentTable::class] as $widgetClass) {
        $reflection = new ReflectionClass($widgetClass);
        $defaults = $reflection->getDefaultProperties();

        expect($defaults['columnSpan'])->toBe(1);
    }
});

it('filtra agentes y afiliaciones sin agente desde el ranking', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ListAffiliations.php');
    $agencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationsByAgencyTable.php');
    $agentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationsByAgentTable.php');
    $query = file_get_contents(dirname(__DIR__, 2).'/app/Support/Affiliations/AffiliationsRankingQuery.php');
    $affiliationsTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($listPage)
        ->toContain('#[On(\'affiliations-filter-by-agent\')]')
        ->toContain('filterAffiliationsByAgent')
        ->toContain('#[On(\'affiliations-filter-by-agency-without-agent\')]')
        ->toContain('filterAffiliationsByAgencyWithoutAgent')
        ->toContain('affiliations-main-table');

    expect($agencyWidget)
        ->toContain('selectAgency')
        ->toContain('->to(AffiliationsByAgentTable::class)')
        ->toContain('affiliations-agent-filter-start');

    expect($agentWidget)
        ->toContain('filterAgentsByAgency')
        ->toContain('viewAgentAffiliations')
        ->toContain('->to(ListAffiliations::class)');

    expect($query)
        ->toContain('Affiliation::query()')
        ->toContain("DB::raw('COUNT(*) as total_affiliations')")
        ->toContain('applyPeriod')
        ->toContain('public static function constrainWithoutAgent');

    expect($affiliationsTable)
        ->toContain("SelectFilter::make('agent_id')")
        ->toContain("SelectFilter::make('code_agency')")
        ->toContain("Filter::make('without_agent')")
        ->toContain("'id' => 'affiliations-main-table'");
});

it('registra los widgets de ranking de afiliaciones individuales en Livewire', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($provider)->not->toBeFalse()
        ->toContain("Livewire::component('app.filament.business.resources.affiliations.widgets.affiliations-by-agency-table', AffiliationsByAgencyTable::class)")
        ->toContain("Livewire::component('app.filament.business.resources.affiliations.widgets.affiliations-by-agent-table', AffiliationsByAgentTable::class)");
});
