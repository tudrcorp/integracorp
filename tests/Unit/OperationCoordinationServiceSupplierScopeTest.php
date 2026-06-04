<?php

declare(strict_types=1);

it('persiste supplier_id y managed_by del médico al crear coordinación desde telemedicina', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationCoordinationServiceController.php');

    expect($contents)
        ->toContain('OperationsSupplierScope::managedByFromDoctor($doctor)')
        ->toContain('OperationsSupplierScope::resolveFromDoctor($doctor)');
});

it('filtra coordinaciones por supplier_id y managed_by ATENMEDI en Operations', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'))
        ->toContain('OperationsSupplierScope::applyCoordinationListScope($query)');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'))
        ->toContain('OperationsSupplierScope::coordinationServiceQuery()');

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_084557_add_supplier_id_to_operation_coordination_services_table.php'))
        ->toContain('operation_coordination_services')
        ->toContain('supplier_id');
});

it('expone resolveFromDoctor y managedByFromDoctor en OperationsSupplierScope', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/OperationsSupplierScope.php');

    expect($contents)
        ->toContain('resolveFromDoctor')
        ->toContain('managedByFromDoctor')
        ->toContain('applyCoordinationListScope')
        ->toContain('coordinationServiceQuery');
});
