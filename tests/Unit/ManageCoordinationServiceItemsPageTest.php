<?php

declare(strict_types=1);

it('registra la página dedicada de gestión de ítems en el recurso', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/OperationCoordinationServiceResource.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ManageCoordinationServiceItems.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    expect($resource)
        ->toContain('ManageCoordinationServiceItems::route')
        ->toContain('manage-items');

    expect($page)
        ->toContain('CoordinationServiceItemsManager::formDefaults')
        ->toContain('CoordinationServiceItemsManager::save')
        ->toContain('fi-coordination-manage-items-page');

    expect($table)
        ->toContain('ManageCoordinationServiceItems::getUrl')
        ->not->toContain('modalHeading(\'Gestionar ítems del servicio\')');
});

it('centraliza la lógica de gestión de ítems en CoordinationServiceItemsManager', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php'))
        ->toContain('final class CoordinationServiceItemsManager')
        ->toContain('function save(')
        ->toContain('function manageServiceActionIsDisabled');
});
