<?php

declare(strict_types=1);

use App\Support\Operations\OperationServiceOrderProviderSelection;
use App\Support\Operations\OperationServiceOrderUnregisteredProviderRegistrar;

it('valida los campos minimos del registro de proveedor no convenido', function (): void {
    expect(OperationServiceOrderUnregisteredProviderRegistrar::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => null,
    ]))->toContain('jurídico o natural');

    expect(OperationServiceOrderUnregisteredProviderRegistrar::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'natural',
        'unregistered_name' => '',
        'unregistered_rif' => 'V-1',
    ]))->toContain('nombre o razón social');

    expect(OperationServiceOrderUnregisteredProviderRegistrar::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'juridico',
        'unregistered_name' => 'Clínica X',
        'unregistered_rif' => '',
    ]))->toContain('C.I. o R.I.F.');

    expect(OperationServiceOrderUnregisteredProviderRegistrar::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'natural',
        'unregistered_name' => 'Dr. Pérez',
        'unregistered_rif' => 'V-123',
    ]))->toBeNull();

    expect(OperationServiceOrderUnregisteredProviderRegistrar::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'juridico',
        'unregistered_name' => 'Clínica X',
        'unregistered_rif' => 'J-123',
        'unregistered_phone' => null,
        'unregistered_correo_principal' => null,
        'unregistered_ubicacion_principal' => null,
    ]))->toBeNull();
});

it('cuenta el toggle de no convenido como unica seleccion de proveedor', function (): void {
    expect(OperationServiceOrderProviderSelection::selectedCount([
        'doctor_nurse_id' => null,
        'supplier_id' => null,
        'register_unregistered_provider' => true,
    ]))->toBe(1);

    expect(OperationServiceOrderProviderSelection::validationMessage([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'natural',
        'unregistered_name' => 'Proveedor',
        'unregistered_rif' => 'J-1',
    ]))->toBeNull();
});

it('expone el formulario simplificado de pre registro', function (): void {
    $unregisteredPath = dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderUnregisteredProviderFormFields.php';

    expect(file_get_contents($unregisteredPath))
        ->toContain('Nombre / Razón social')
        ->toContain('C.I. / R.I.F.')
        ->toContain('unregistered_phone')
        ->toContain('Correo electrónico')
        ->toContain("->label('Dirección')")
        ->not->toContain('unregistered_state_id')
        ->not->toContain('unregistered_speciality');
});
