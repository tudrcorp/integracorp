<?php

declare(strict_types=1);

use App\Support\Operations\OperationServiceOrderCoveredPricingFormFields;

function coveredPricingFormFieldsSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderCoveredPricingFormFields.php'
    );
}

function coveredPricingManageFormSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php'
    );
}

function coveredPricingManagerSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php'
    );
}

function coveredPricingControllerSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php'
    );
}

it('define campos de precio USD, VES y tasa BCV para órdenes cubiertas', function (): void {
    expect(coveredPricingFormFieldsSource())
        ->toContain("TextInput::make('service_order_bcv_rate')")
        ->toContain("TextInput::make('service_order_price_usd')")
        ->toContain("TextInput::make('service_order_price_ves')")
        ->toContain('syncVesFromUsd')
        ->toContain('pricingPayloadFromData')
        ->toContain('requiresPricing');
});

it('calcula el payload de precios a partir del USD y la tasa BCV', function (): void {
    $payload = OperationServiceOrderCoveredPricingFormFields::pricingPayloadFromData([
        'service_order_price_usd' => 25,
        'service_order_bcv_rate' => 40,
        'service_order_price_ves' => 1000,
    ]);

    expect($payload)->toBe([
        'currency' => 'USD',
        'tasa_bcv' => 40.0,
        'total_amount_usd' => 25.0,
        'total_amount_ves' => 1000.0,
    ]);
});

it('calcula bolívares cuando no vienen en el formulario', function (): void {
    $payload = OperationServiceOrderCoveredPricingFormFields::pricingPayloadFromData([
        'service_order_price_usd' => 10,
        'service_order_bcv_rate' => 36.5,
    ]);

    expect($payload)->not->toBeNull()
        ->and($payload['total_amount_ves'])->toBe(365.0);
});

it('requiere precio cuando hay proveedor natural o jurídico seleccionado', function (): void {
    expect(OperationServiceOrderCoveredPricingFormFields::requiresPricing([
        'supplier_id' => 12,
    ]))->toBeTrue();

    expect(OperationServiceOrderCoveredPricingFormFields::requiresPricing([
        'doctor_nurse_id' => 4,
    ]))->toBeTrue();

    expect(OperationServiceOrderCoveredPricingFormFields::requiresPricing([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'natural',
    ]))->toBeTrue();

    expect(OperationServiceOrderCoveredPricingFormFields::requiresPricing([
        'register_unregistered_provider' => true,
        'unregistered_provider_type' => 'juridico',
    ]))->toBeFalse();

    expect(OperationServiceOrderCoveredPricingFormFields::requiresPricing([]))->toBeFalse();
});

it('valida que el precio en dólares sea mayor a cero', function (): void {
    expect(OperationServiceOrderCoveredPricingFormFields::validationMessage([
        'supplier_id' => 1,
        'service_order_price_usd' => null,
        'service_order_bcv_rate' => 40,
    ]))->toContain('precio en dólares');

    expect(OperationServiceOrderCoveredPricingFormFields::validationMessage([
        'supplier_id' => 1,
        'service_order_price_usd' => 15,
        'service_order_bcv_rate' => 40,
    ]))->toBeNull();
});

it('integra los campos de precio en información operativa y proveedor no convenido natural', function (): void {
    expect(coveredPricingManageFormSource())
        ->toContain('OperationServiceOrderCoveredPricingFormFields::components()');

    expect(file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderUnregisteredProviderFormFields.php'
    ))->toContain('OperationServiceOrderCoveredPricingFormFields::components()');

    expect(file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'
    ))
        ->toContain('OperationServiceOrderCoveredPricingFormFields::components()')
        ->toContain('OperationServiceOrderCoveredPricingFormFields::pricingPayloadFromData');
});

it('valida y persiste el precio al crear la orden directa de ítems cubiertos', function (): void {
    expect(coveredPricingManagerSource())
        ->toContain('OperationServiceOrderCoveredPricingFormFields::validationMessage')
        ->toContain("'service_order_price_usd' => null")
        ->toContain("'service_order_price_ves' => null")
        ->toContain("'service_order_bcv_rate'");

    expect(coveredPricingControllerSource())
        ->toContain("'currency' => \$data['currency'] ?? null")
        ->toContain("'tasa_bcv' => \$data['tasa_bcv'] ?? null")
        ->toContain("'total_amount_usd' => \$data['total_amount_usd'] ?? null")
        ->toContain("'total_amount_ves' => \$data['total_amount_ves'] ?? null");
});
