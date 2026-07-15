<?php

declare(strict_types=1);

it('expone Ver Jerarquía en el panel de agencias generales', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/GeneralPanelProvider.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/General/Pages/ViewMyHierarchy.php';
    $themePath = dirname(__DIR__, 2).'/resources/css/filament/general/theme.css';
    $sharedViewPath = dirname(__DIR__, 2).'/resources/views/filament/shared/pages/view-my-hierarchy.blade.php';

    $providerSource = file_get_contents($providerPath);
    $pageSource = file_get_contents($pagePath);
    $themeSource = file_get_contents($themePath);
    $sharedViewSource = file_get_contents($sharedViewPath);

    expect($providerSource)
        ->toContain("Action::make('viewHierarchy')")
        ->toContain("->label('Ver Jerarquía')")
        ->toContain("url('/general/ver-jerarquia')")
        ->toContain('ViewMyHierarchy::class');

    expect($pageSource)
        ->toContain('CommercialHierarchyFlowchart::renderForAgency')
        ->toContain("protected static ?string \$slug = 'ver-jerarquia'")
        ->toContain('code_agency');

    expect($themeSource)
        ->toContain("@import '../shared/hierarchy-flowchart.css';");

    expect($sharedViewSource)
        ->toContain('getHierarchyDiagram()')
        ->toContain('Jerarquía comercial');
});

it('expone Ver Jerarquía en el panel de agencias master', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/MasterPanelProvider.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Master/Pages/ViewMyHierarchy.php';

    $providerSource = file_get_contents($providerPath);
    $pageSource = file_get_contents($pagePath);

    expect($providerSource)
        ->toContain("Action::make('viewHierarchy')")
        ->toContain("->label('Ver Jerarquía')")
        ->toContain("url('/master/ver-jerarquia')")
        ->toContain('ViewMyHierarchy::class');

    expect($pageSource)
        ->toContain('CommercialHierarchyFlowchart::renderForAgency')
        ->toContain("protected static ?string \$slug = 'ver-jerarquia'")
        ->toContain('code_agency');
});

it('marca Esta agencia en nodos de agencia resaltados del diagrama', function (): void {
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($flowchartSource)
        ->toContain('Esta agencia')
        ->toContain('Este agente');
});
