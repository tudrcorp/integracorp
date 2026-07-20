<?php

declare(strict_types=1);

it('expone el botón Ver Jerarquía en el menú de usuario del panel de agentes', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/AgentsPanelProvider.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Agents/Pages/ViewMyHierarchy.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/shared/pages/view-my-hierarchy.blade.php';
    $agentsThemePath = dirname(__DIR__, 2).'/resources/css/filament/agents/theme.css';
    $sharedCssPath = dirname(__DIR__, 2).'/resources/css/filament/shared/hierarchy-flowchart.css';
    $agentsInfolistPath = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Schemas/AgentInfolist.php';
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';

    $providerSource = file_get_contents($providerPath);
    $pageSource = file_get_contents($pagePath);
    $viewSource = file_get_contents($viewPath);
    $agentsThemeSource = file_get_contents($agentsThemePath);
    $sharedCssSource = file_get_contents($sharedCssPath);
    $agentsInfolistSource = file_get_contents($agentsInfolistPath);
    $flowchartSource = file_get_contents($flowchartPath);

    expect($providerSource)
        ->toContain("Action::make('viewHierarchy')")
        ->toContain("->label('Ver Jerarquía')")
        ->toContain("url('/agents/ver-jerarquia')")
        ->toContain('ViewMyHierarchy::class');

    expect($pageSource)
        ->toContain('CommercialHierarchyFlowchart::renderForAgent')
        ->toContain("protected static ?string \$slug = 'ver-jerarquia'")
        ->toContain('shouldRegisterNavigation(): bool');

    expect($viewSource)
        ->toContain('getHierarchyDiagram()')
        ->toContain('Jerarquía comercial');

    expect($agentsThemeSource)
        ->toContain("@import '../shared/hierarchy-flowchart.css';");

    expect($sharedCssSource)
        ->toContain('.tdg-hierarchy-flowchart-shell')
        ->toContain('.tdg-hierarchy-flowchart--interactive');

    expect($agentsInfolistSource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgent');

    expect($flowchartSource)
        ->toContain('buildInteractiveHierarchyTreeForHeadquarters')
        ->toContain("strtoupper(\$agencyCode) === 'TDG-100'");
});
