<?php

declare(strict_types=1);

it('filtra segmentos de agencia master en contexto master', function (): void {
    $flowchartPath = __DIR__.'/../../app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($flowchartSource)->not->toBeFalse()
        ->toContain('VIEWER_MASTER')
        ->toContain('filterCommercialCodeSegmentsForViewer')
        ->toContain("str_starts_with(\$segment, 'AGENCIA MASTER ·')");
});

it('filtra segmentos de agencia master y general en contexto general', function (): void {
    $flowchartPath = __DIR__.'/../../app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($flowchartSource)->not->toBeFalse()
        ->toContain('VIEWER_GENERAL')
        ->toContain("str_starts_with(\$segment, 'AGENCIA GENERAL ·')")
        ->toContain('agentsUnderGeneralAgencyQuery');
});

it('filtra subagentes bajo agente responsable en panel agents', function (): void {
    $flowchartPath = __DIR__.'/../../app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $tablePath = __DIR__.'/../../app/Filament/Agents/Resources/Agents/Tables/AgentsTable.php';

    $flowchartSource = file_get_contents($flowchartPath);
    $tableSource = file_get_contents($tablePath);

    expect($flowchartSource)->not->toBeFalse()
        ->toContain('VIEWER_AGENT')
        ->toContain('agentsUnderAgentQuery')
        ->toContain("str_starts_with(\$segment, 'SUB AGENTE ·')");

    expect($tableSource)->not->toBeFalse()
        ->toContain('CommercialHierarchyFlowchart::agentsUnderAgentQuery')
        ->toContain('CommercialHierarchyFlowchart::VIEWER_AGENT');
});

it('expone consulta de agentes bajo agencia general en la tabla general', function (): void {
    $tablePath = __DIR__.'/../../app/Filament/General/Resources/Agents/Tables/AgentsTable.php';
    $tableSource = file_get_contents($tablePath);

    expect($tableSource)->not->toBeFalse()
        ->toContain('CommercialHierarchyFlowchart::agentsUnderGeneralAgencyQuery')
        ->toContain('CommercialHierarchyFlowchart::VIEWER_GENERAL');
});

it('usa contexto master en la tabla master de agentes', function (): void {
    $tablePath = __DIR__.'/../../app/Filament/Master/Resources/Agents/Tables/AgentsTable.php';
    $tableSource = file_get_contents($tablePath);

    expect($tableSource)->not->toBeFalse()
        ->toContain('CommercialHierarchyFlowchart::VIEWER_MASTER');
});
