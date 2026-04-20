<?php

declare(strict_types=1);

it('muestra modal de detalle jerarquico al hacer click en nro de venta', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Commissions/Tables/CommissionsTable.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/administration/commissions/modals/commission-hierarchy-details-modal.blade.php';

    $tableContents = file_get_contents($tablePath);
    $modalContents = file_get_contents($modalPath);

    expect($tableContents)
        ->toContain('view_commission_hierarchy_detail')
        ->toContain('Comisiones de venta #')
        ->toContain('commission-hierarchy-details-modal')
        ->toContain("->label('Nro. Venta')");

    expect($modalContents)
        ->toContain('Jerarquía de comisiones generadas')
        ->toContain('Nivel Master')
        ->toContain('Nivel General')
        ->toContain('Nivel Agente')
        ->toContain('Contexto de la venta');
});
