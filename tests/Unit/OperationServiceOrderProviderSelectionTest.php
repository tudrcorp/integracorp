<?php

declare(strict_types=1);

use App\Support\Operations\OperationServiceOrderProviderSelection;

it('exige exactamente un proveedor en la orden de servicio', function (): void {
    expect(OperationServiceOrderProviderSelection::validationMessage([
        'doctor_nurse_id' => null,
        'supplier_id' => null,
        'supplier_external' => null,
    ]))->toContain('exactamente un proveedor');

    expect(OperationServiceOrderProviderSelection::validationMessage([
        'doctor_nurse_id' => 1,
        'supplier_id' => 2,
        'supplier_external' => null,
    ]))->toContain('Solo puede registrar un proveedor');

    expect(OperationServiceOrderProviderSelection::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'natural',
        'unregistered_name' => 'Clínica externa',
        'unregistered_rif' => 'J-999',
        'unregistered_phone' => '04141234567',
    ]))->toBeNull();

    expect(OperationServiceOrderProviderSelection::validationMessage([
        'doctor_nurse_id' => 5,
        'supplier_id' => null,
        'supplier_external' => '',
    ]))->toBeNull();
});

it('normaliza la orden para persistir un solo proveedor existente', function (): void {
    expect(OperationServiceOrderProviderSelection::normalizeFromFormData([
        'doctor_nurse_id' => 3,
        'supplier_id' => 9,
        'supplier_external' => 'Externo',
        'register_unregistered_provider' => false,
    ]))->toBe([
        'doctor_nurse_id' => 3,
        'supplier_id' => null,
        'supplier_external' => null,
    ]);

    expect(OperationServiceOrderProviderSelection::normalizeFromFormData([
        'doctor_nurse_id' => null,
        'supplier_id' => 12,
        'supplier_external' => null,
    ]))->toBe([
        'doctor_nurse_id' => null,
        'supplier_id' => 12,
        'supplier_external' => null,
    ]);

    expect(OperationServiceOrderProviderSelection::normalizeFromFormData([
        'doctor_nurse_id' => null,
        'supplier_id' => null,
        'supplier_external' => ' Farmacia ABC ',
    ]))->toBe([
        'doctor_nurse_id' => null,
        'supplier_id' => null,
        'supplier_external' => 'Farmacia ABC',
    ]);
});
