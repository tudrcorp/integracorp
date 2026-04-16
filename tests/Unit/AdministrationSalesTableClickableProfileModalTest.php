<?php

declare(strict_types=1);

it('configura acciones clickeables para abrir fichas de agencia y agente en ventas', function (): void {
    $salesTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php';

    expect(file_exists($salesTablePath))->toBeTrue();

    $contents = file_get_contents($salesTablePath);

    expect($contents)->toContain('viewAgencyProfile');
    expect($contents)->toContain('viewAgentProfile');
    expect($contents)->toContain('filament.administration.sales.modals.agency-profile-modal');
    expect($contents)->toContain('filament.administration.sales.modals.agent-profile-modal');
});

it('tiene las vistas blade de modal para ficha de agencia y agente', function (): void {
    $workspaceRoot = dirname(__DIR__, 2);

    expect(file_exists($workspaceRoot.'/resources/views/filament/administration/sales/modals/agency-profile-modal.blade.php'))->toBeTrue();
    expect(file_exists($workspaceRoot.'/resources/views/filament/administration/sales/modals/agent-profile-modal.blade.php'))->toBeTrue();
});
