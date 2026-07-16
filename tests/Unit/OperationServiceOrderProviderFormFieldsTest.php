<?php

declare(strict_types=1);

it('define proveedor natural jurídico y externo en una fila de tres columnas', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderProviderFormFields.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';

    expect(file_get_contents($providerPath))
        ->toContain("Select::make('doctor_nurse_id')")
        ->toContain('Proveedor natural')
        ->toContain("Select::make('supplier_id')")
        ->toContain('Proveedor jurídico')
        ->toContain('register_unregistered_provider');

    expect(file_get_contents($tablePath))
        ->toContain('OperationServiceOrderProviderFormFields::components()')
        ->toContain('OperationServiceOrderCoveredPricingFormFields::components()')
        ->toContain("Step::make('Proveedor no convenido')")
        ->toContain('OperationServiceOrderProviderSelection::validationMessage')
        ->toContain('buildServiceOrderPayload');
});

it('persiste doctor_nurse_id al crear orden de servicio', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/OperationServiceOrder.php';

    expect(file_get_contents($controllerPath))
        ->toContain('OperationServiceOrderProviderSelection::validationMessage')
        ->toContain('OperationServiceOrderProviderSelection::resolveProviders');

    expect(file_get_contents($modelPath))
        ->toContain('doctor_nurse_id')
        ->toContain('function doctorNurse()');
});
