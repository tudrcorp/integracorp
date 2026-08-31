<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\DoctorNurse;
use App\Models\State;
use App\Models\Supplier;
use App\Support\Operations\OperationServiceOrderProviderContacts;

it('resuelve correo, teléfono y dirección del proveedor jurídico', function (): void {
    $supplier = new Supplier([
        'name' => 'Clínica Central',
        'correo_principal' => 'clinica@example.com',
        'personal_phone' => '04141234567',
        'ubicacion_principal' => 'Av. Bolívar, Valencia',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $contacts = OperationServiceOrderProviderContacts::fromModels(null, $supplier);

    expect($contacts['email'])->toBe('clinica@example.com')
        ->and($contacts['phone'])->toBe('04141234567')
        ->and($contacts['address'])->toBe('Av. Bolívar, Valencia')
        ->and($contacts['name'])->toBe('Clínica Central')
        ->and($contacts['missing'])->toBe([]);
});

it('usa el teléfono local del jurídico cuando no hay personal', function (): void {
    $supplier = new Supplier([
        'name' => 'Centro Diagnóstico',
        'correo_principal' => 'dx@example.com',
        'personal_phone' => null,
        'local_phone' => '02125551234',
        'ubicacion_principal' => 'La Castellana',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    expect(OperationServiceOrderProviderContacts::fromSupplier($supplier)['phone'])->toBe('02125551234');
});

it('completa dirección del jurídico con estado y ciudad', function (): void {
    $supplier = new Supplier([
        'name' => 'Clínica del Llano',
        'correo_principal' => 'llano@example.com',
        'personal_phone' => '04140001111',
        'ubicacion_principal' => null,
    ]);
    $supplier->setRelation('state', new State(['definition' => 'GUARICO']));
    $supplier->setRelation('city', new City(['definition' => 'CALABOZO']));

    expect(OperationServiceOrderProviderContacts::fromSupplier($supplier)['address'])->toBe('GUARICO — CALABOZO');
});

it('resuelve correo, teléfono y dirección del proveedor natural', function (): void {
    $doctor = new DoctorNurse([
        'name' => 'Dra. Ana Pérez',
        'correo_principal' => 'ana@example.com',
        'personal_phone' => '04149998877',
        'ubicacion_principal' => 'Consulta pediátrica, La Castellana',
    ]);

    $contacts = OperationServiceOrderProviderContacts::fromModels($doctor, null);

    expect($contacts['email'])->toBe('ana@example.com')
        ->and($contacts['phone'])->toBe('04149998877')
        ->and($contacts['address'])->toBe('Consulta pediátrica, La Castellana')
        ->and($contacts['missing'])->toBe([]);
});

it('prioriza el jurídico cuando hay ambos modelos', function (): void {
    $supplier = new Supplier([
        'name' => 'Clínica Convenida',
        'correo_principal' => 'juridico@example.com',
        'personal_phone' => '04141111111',
        'ubicacion_principal' => 'Caracas',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);
    $doctor = new DoctorNurse([
        'name' => 'Dr. Natural',
        'correo_principal' => 'natural@example.com',
        'personal_phone' => '04142222222',
        'ubicacion_principal' => 'Valencia',
    ]);

    expect(OperationServiceOrderProviderContacts::fromModels($doctor, $supplier)['name'])->toBe('Clínica Convenida');
});

it('marca como faltantes correo, teléfono y dirección incompletos', function (): void {
    $supplier = new Supplier([
        'name' => 'Proveedor Incompleto',
        'correo_principal' => null,
        'personal_phone' => null,
        'local_phone' => null,
        'ubicacion_principal' => null,
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $contacts = OperationServiceOrderProviderContacts::fromSupplier($supplier);

    expect($contacts['email'])->toBeNull()
        ->and($contacts['phone'])->toBeNull()
        ->and($contacts['address'])->toBeNull()
        ->and($contacts['missing'])->toBe(['correo', 'teléfono', 'dirección']);
});

it('arma la lista en español de datos faltantes', function (): void {
    expect(OperationServiceOrderProviderContacts::spanishList(['correo']))->toBe('correo')
        ->and(OperationServiceOrderProviderContacts::spanishList(['correo', 'teléfono']))->toBe('correo y teléfono')
        ->and(OperationServiceOrderProviderContacts::spanishList(['correo', 'teléfono', 'dirección']))
        ->toBe('correo, teléfono y dirección');
});

it('el formulario de orden coloca el proveedor primero, autollena contacto y deja la descripción libre', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');
    $providerFields = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderProviderFormFields.php');
    $contacts = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderProviderContacts.php');

    $providerPos = strpos($form, 'OperationServiceOrderProviderFormFields::components(');
    $orderNumberPos = strpos($form, "TextInput::make('order_number')");
    $emailPos = strpos($form, "TextInput::make('supplier_notify_email')");
    $addressPos = strpos($form, "TextInput::make('supplier_notify_address')");
    $descriptionPos = strpos($form, "Textarea::make('service_order_description')");

    expect($providerPos)->toBeInt()
        ->and($orderNumberPos)->toBeInt()
        ->and($emailPos)->toBeInt()
        ->and($addressPos)->toBeInt()
        ->and($descriptionPos)->toBeInt()
        ->and($providerPos)->toBeLessThan($orderNumberPos)
        ->and($orderNumberPos)->toBeLessThan($emailPos)
        ->and($emailPos)->toBeLessThan($addressPos)
        ->and($addressPos)->toBeLessThan($descriptionPos);

    expect($form)
        ->toContain("Grid::make(['default' => 1, 'md' => 3])")
        ->toContain('OperationServiceOrderProviderContacts::hasCatalogSelection')
        ->toContain("TextInput::make('supplier_notify_address')")
        ->toContain('Dirección del proveedor')
        ->toContain('equipo de Proveedores')
        ->toContain("Textarea::make('service_order_description')")
        ->not->toContain('supplierNeedsManualNotifyContacts')
        ->not->toContain("TextInput::make('service_order_description')")
        ->not->toContain('->maxLength(500)');

    expect($providerFields)
        ->toContain('OperationServiceOrderProviderContacts::applyFromDoctorNurseId')
        ->toContain('OperationServiceOrderProviderContacts::applyFromSupplierId')
        ->toContain('OperationServiceOrderProviderContacts::clearForm');

    expect($contacts)
        ->toContain('Ficha del proveedor incompleta')
        ->toContain('equipo de Proveedores')
        ->toContain('No los invente en esta orden.')
        ->toContain('->persistent()');
});
