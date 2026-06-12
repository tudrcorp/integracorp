<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('delega al infolist compartido de agencias en business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('SharedAgencyInfolist::configure($schema)');
});

it('expone la relación observationCommercialStructures en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("RepeatableEntry::make('observationCommercialStructures')");
});

it('formatea fechas legadas d/m/Y con FilamentDateDisplay en lugar de TextEntry::date', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('FilamentDateDisplay::toDmy');
    expect($source)->not->toContain("->date('d/m/Y')");
});

it('agrupa secciones alineadas al formulario y usa rejilla de cinco columnas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Section::make('Información general de la agencia')");
    expect($source)->toContain("Section::make('Contacto alternativo')");
    expect($source)->toContain('Grid::make(5)');
    expect($source)->toContain("Text::make('Dirección de la Agencia en Venezuela')");
    expect($source)->toContain("Text::make('Dirección de la Agencia en Otros Paises')");
    expect($source)->toContain('IOS_ADDRESS_VENEZUELA_CARD');
    expect($source)->toContain('IOS_ADDRESS_INTERNATIONAL_CARD');
    expect($source)->toContain("TextEntry::make('address_other_country')");
    expect($source)->toContain('hasInternationalAddress');
});

it('incluye una pestaña de jerarquía para resolver general, master y TUDRENCASA', function (): void {
    $agencyPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $agencySource = file_get_contents($agencyPath);
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($agencySource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Section::make('Jerarquía comercial')")
        ->toContain("TextEntry::make('hierarchy_diagram')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgency')
        ->toContain('Master → General → Agente → Subagente');

    expect($flowchartSource)
        ->toContain('tdg-hierarchy-flowchart-shell')
        ->toContain('buildInteractiveHierarchyTree')
        ->toContain('renderInteractiveHierarchyTree')
        ->toContain('buildAgentTreeForAgencyCode')
        ->toContain('tdg-hierarchy-flowchart__general-stack')
        ->toContain('expandable--general-agents')
        ->toContain('hierarchy-general-agents-dock')
        ->toContain('x-teleport')
        ->toContain('renderExpandActiveIndicator')
        ->toContain('expand-active-indicator')
        ->toContain('is-active')
        ->toContain('horizontalAgentsLayout')
        ->toContain('expandable--horizontal-agents')
        ->toContain('renderExpandableAgentBranch')
        ->toContain('activeGeneralBranch')
        ->toContain('toggleGeneralAgents')
        ->toContain('toggleMasterAgents')
        ->toContain('teleportTo')
        ->toContain('hierarchy-subagents-dock')
        ->toContain('expandable--subagents-panel')
        ->toContain('activeSubagentBranch')
        ->toContain('toggleSubagents')
        ->toContain('tdg-hierarchy-slider')
        ->toContain('masterAgentsOpen')
        ->toContain('TUDRENCASA')
        ->toContain('structureSummaryForAgency')
        ->toContain('renderMasterHierarchyNode')
        ->toContain('buildMasterStructureCounts')
        ->toContain('node-meta--structure-summary')
        ->toContain('node-structure-summary')
        ->toContain('hierarchyAgentNodePayload')
        ->toContain('mb_strtoupper')
        ->toContain('Agencia general')
        ->toContain('Sub-Agente(s)')
        ->toContain('generalAgenciesUnderMasterCode')
        ->toContain('resolveAgentOwnerCodesForAgencyCode')
        ->toContain('applyAgentOwnerCodeScopeForAgency')
        ->toContain('resolveAgencyByOwnerCode')
        ->toContain('legend-item--subagent')
        ->not->toContain('renderStructureCardsByAgency');
});

it('aplica estilos de contenedor de tabs alineados a telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain('->persistTab()')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.25rem]');
});
