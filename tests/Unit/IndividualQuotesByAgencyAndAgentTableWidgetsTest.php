<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesByAgencyTable;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesByAgentTable;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use Filament\Widgets\TableWidget;

it('registra los widgets de tabla por agencia y agente debajo del gráfico', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Pages/ListIndividualQuotes.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->and($code)->toContain('TotalIndividualQuoteChart::class')
        ->and($code)->toContain('IndividualQuotesByAgencyTable::class')
        ->and($code)->toContain('IndividualQuotesByAgentTable::class');

    $chartPos = strpos($code, 'TotalIndividualQuoteChart::class');
    $agencyPos = strpos($code, 'IndividualQuotesByAgencyTable::class');
    $agentPos = strpos($code, 'IndividualQuotesByAgentTable::class');

    expect($chartPos)->toBeInt()->toBeLessThan($agencyPos)
        ->and($agencyPos)->toBeLessThan($agentPos);
});

it('define el widget de cotizaciones por agencia con columnas requeridas', function (): void {
    expect(class_exists(IndividualQuotesByAgencyTable::class))->toBeTrue()
        ->and(is_subclass_of(IndividualQuotesByAgencyTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/IndividualQuotesByAgencyTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('IndividualQuotesRankingTableUi::apply')
        ->toContain("variant: 'agency'")
        ->toContain("nameAttribute: 'name_corporative'")
        ->toContain("->whereNull('individual_quotes.agent_id')")
        ->toContain("->orWhere('individual_quotes.agent_id', '')")
        ->toContain('->groupBy(')
        ->toContain("'agencies.name_corporative'");
});

it('define el widget de cotizaciones por agente con columnas requeridas', function (): void {
    expect(class_exists(IndividualQuotesByAgentTable::class))->toBeTrue()
        ->and(is_subclass_of(IndividualQuotesByAgentTable::class, TableWidget::class))->toBeTrue();

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/IndividualQuotesByAgentTable.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse()
        ->toContain('IndividualQuotesRankingTableUi::apply')
        ->toContain("variant: 'agent'")
        ->toContain("nameAttribute: 'name'")
        ->toContain("->whereNotNull('individual_quotes.agent_id')")
        ->toContain("->where('individual_quotes.agent_id', '!=', '')")
        ->toContain('->groupBy(')
        ->toContain("'agents.name'");
});

it('coloca las tablas lado a lado en la misma fila', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Pages/ListIndividualQuotes.php';
    $listCode = file_get_contents($listPath);

    expect($listCode)->not->toBeFalse()
        ->toContain('getHeaderWidgetsColumns')
        ->toContain("'lg' => 2");

    foreach ([IndividualQuotesByAgencyTable::class, IndividualQuotesByAgentTable::class] as $widgetClass) {
        $reflection = new ReflectionClass($widgetClass);
        $defaults = $reflection->getDefaultProperties();

        expect($defaults['columnSpan'])->toBe(1);
    }
});

it('aplica UI iOS compacta con ranking y sin barra de progreso', function (): void {
    $root = dirname(__DIR__, 2);

    $ui = file_get_contents($root.'/app/Support/Filament/IndividualQuotesRankingTableUi.php');
    $css = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    $widgetView = file_get_contents($root.'/resources/views/filament/widgets/individual-quotes-ranking-table-widget.blade.php');

    expect($ui)->toContain('->rowIndex()')
        ->toContain('individual-quotes-ranking-table-ios')
        ->toContain("TextColumn::make('total_quotes')")
        ->toContain('mb_strtoupper($state')
        ->toContain('->striped()')
        ->toContain('->defaultPaginationPageOption(8)')
        ->toContain('->paginated([8, 16, 25, 50])')
        ->not->toContain('individual-quote-ranking-total');

    expect($css)->toContain('.fi-iq-ranking-table-widget')
        ->toContain('.individual-quotes-ranking-table-ios')
        ->toContain('.iq-ranking-rank-badge--gold')
        ->toContain('.iq-ranking-total-cell')
        ->toContain('.fi-ta-row.iq-ranking-row--selected')
        ->toContain('tbody:has(.iq-ranking-row--selected)')
        ->toContain('.individual-quotes-ranking-table-ios--agent .fi-ta-cell')
        ->not->toContain('.iq-ranking-total-bar__fill--agency');

    expect($widgetView)->toContain('$widgetClass')
        ->toContain('getRankingTableVariant');

    expect(IndividualQuotesRankingTableUi::tableClass('agency'))
        ->toBe('individual-quotes-ranking-table-ios individual-quotes-ranking-table-ios--agency');
});

it('filtra agentes al seleccionar una agencia desde la tabla izquierda', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Pages/ListIndividualQuotes.php');
    $agencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/IndividualQuotesByAgencyTable.php');
    $agentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/IndividualQuotesByAgentTable.php');

    expect($listPage)->toContain('selectAgencyForAgentFilter')
        ->toContain('clearAgencyAgentFilter')
        ->toContain('filteredAgencyCode')
        ->toContain('selectedAgencyId')
        ->toContain('#[On(\'individual-quotes-agency-selected\')]');

    expect($agencyWidget)->toContain('selectAgencyByKey')
        ->toContain('individual-quotes-agency-selected')
        ->toContain('agencyCode: $agency->code')
        ->toContain("->recordAction('selectAgencyByKey')")
        ->toContain('#[Reactive]')
        ->not->toContain('recordActions');

    expect($agentWidget)->toContain('#[Reactive]')
        ->toContain('public ?string $filteredAgencyCode')
        ->toContain("->where('individual_quotes.owner_code', \$this->filteredAgencyCode)")
        ->toContain('Ver todos los agentes')
        ->not->toContain('#[On(\'individual-quotes-agency-selected\')]');
});
