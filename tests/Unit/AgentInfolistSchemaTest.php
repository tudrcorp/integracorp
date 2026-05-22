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
        ->toContain('Diagrama visual para validar si la agencia es general, master y su relación con TUDRENCASA.')
        ->toContain("TextEntry::make('hierarchy_diagram')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgent');

    expect($flowchartSource)
        ->toContain('tdg-hierarchy-flowchart-shell')
        ->toContain('renderForAgent')
        ->toContain('tdg-hierarchy-flowchart')
        ->toContain('highlightAgentId')
        ->not->toContain('renderStructureCardsByAgency');
});

it('aplica estilos de contenedor de tabs alineados a telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain('->persistTab()')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.25rem]');
});
