<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Agents\Schemas\AgentInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agente en administracion sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa el infolist compartido de estructura comercial para agentes en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('SharedAgentInfolist::configure($schema)');
});

it('incluye diagrama de jerarquia comercial en administracion via infolist compartido', function (): void {
    $sharedPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $sharedSource = file_get_contents($sharedPath);
    $flowchartSource = file_get_contents($flowchartPath);

    expect($sharedSource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgent');

    expect($flowchartSource)
        ->toContain('renderForAgent')
        ->toContain('highlightAgentId')
        ->toContain('tdg-hierarchy-flowchart--agent-focus')
        ->toContain('resolveInitialExpandState')
        ->toContain('tdg-hierarchy-flowchart-shell')
        ->toContain('hierarchy-subagents-dock')
        ->toContain('activeSubagentBranch');
});

it('hereda estilos de tabs de telemedicina via infolist compartido de agentes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('TABS_CONTAINER')->toContain('->persistTab()');
});
