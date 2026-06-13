<?php

declare(strict_types=1);

it('expone la secuencia jerarquica comercial en la tabla master de agentes', function (): void {
    $tablePath = __DIR__.'/../../app/Filament/Master/Resources/Agents/Tables/AgentsTable.php';
    $flowchartPath = __DIR__.'/../../app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';

    $tableSource = file_get_contents($tablePath);
    $flowchartSource = file_get_contents($flowchartPath);

    expect($tableSource)->not->toBeFalse()
        ->toContain('CommercialHierarchyFlowchart::commercialCodeSequenceForAgent')
        ->toContain('CommercialHierarchyFlowchart::VIEWER_MASTER')
        ->toContain('commercial_code_sequence')
        ->toContain('AGENTES')
        ->toContain('Agencia Master')
        ->toContain('Agencia General')
        ->toContain('Sub Agente');

    expect($flowchartSource)->not->toBeFalse()
        ->toContain('commercialCodeSequenceForAgent')
        ->toContain('AGENCIA MASTER ·')
        ->toContain('AGENCIA GENERAL ·')
        ->toContain('AGENTE · AGT-000')
        ->toContain('SUB AGENTE · AGT-000');
});
