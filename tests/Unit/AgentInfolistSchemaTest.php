<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Schemas\AgentInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agente business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('delega al infolist compartido de agentes en business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('SharedAgentInfolist::configure($schema)');
});

it('expone la relación observationCommercialStructures en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("RepeatableEntry::make('observationCommercialStructures')");
});

it('formatea fecha de nacimiento con FilamentDateDisplay para cadenas d/m/Y', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('FilamentDateDisplay::toDmy');
    expect($source)->toContain("TextEntry::make('birth_date')");
});

it('incluye una pestaña de jerarquía comercial con diagrama visual', function (): void {
    $agentPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $agentSource = file_get_contents($agentPath);
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($agentSource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Section::make('Jerarquía comercial')")
        ->toContain('Master → General → Agente → Subagente')
        ->toContain("TextEntry::make('hierarchy_diagram')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgent');

    expect($flowchartSource)
        ->toContain('tdg-hierarchy-flowchart-shell')
        ->toContain('renderForAgent')
        ->toContain('buildInteractiveHierarchyTree')
        ->toContain('renderInteractiveHierarchyTree')
        ->toContain('buildAgentTreeForAgencyCode')
        ->toContain('tdg-hierarchy-flowchart--interactive')
        ->toContain('tdg-hierarchy-flowchart--agent-focus')
        ->toContain('resolveAgentFocusPath')
        ->toContain('applyAgentFocusPathToTree')
        ->toContain('is_focus_path')
        ->toContain('node--focus-path')
        ->toContain('general-stack--focus-path')
        ->toContain('agent-branch--focus-path')
        ->toContain('tdg-hierarchy-flowchart__general-stack')
        ->toContain('expandable--general-agents')
        ->toContain('hierarchy-general-agents-dock')
        ->toContain('x-teleport')
        ->toContain('renderExpandActiveIndicator')
        ->toContain('expand-active-indicator')
        ->toContain('is_highlighted')
        ->toContain('node--highlighted-person')
        ->toContain('node-highlight-badge')
        ->toContain('node-highlight-ring')
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
        ->toContain('hierarchySliderAlpineData')
        ->toContain('initSlider')
        ->toContain('scrollToSlide')
        ->toContain('data-hierarchy-slider')
        ->toContain('highlightAgentId')
        ->toContain('resolveInitialExpandState')
        ->toContain('resolveInitialExpandStateForHighlightedAgent')
        ->toContain('renderMasterHierarchyNode')
        ->toContain('buildMasterStructureCounts')
        ->toContain('node-meta--structure-summary')
        ->toContain('hierarchyAgentNodePayload')
        ->toContain('mb_strtoupper')
        ->toContain('Sub-Agente(s)')
        ->not->toContain('renderStructureCardsByAgency');
});

it('aplica estilos de contenedor de tabs alineados a telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain('->persistTab()')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.25rem]')
        ->toContain('IOS_ADDRESS_VENEZUELA_CARD')
        ->toContain("Text::make('Dirección en Venezuela')")
        ->toContain('AgentAddressClipboardFormat::venezuela');
});
