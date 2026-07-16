<?php

declare(strict_types=1);

it('resuelve el tipo de gestión TPA/RETAIL al servicio específico', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    expect($manager)
        ->toContain('resolveManagementTypeForCoordination')
        ->toContain('RegisterTpaRetailServicesAction::ensureStandaloneManagementItem')
        ->toContain("'category' => \$isTpaStandaloneServiceItem ? 'Servicio' : 'Especialista'");

    expect($table)
        ->toContain('RegisterTpaRetailServicesAction::isStandaloneSpecificService($type)');
});
