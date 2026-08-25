<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\DoctorNurse;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\State;
use App\Models\Supplier;
use App\Support\Operations\OperationServiceOrderProviderSummary;

function providerSummaryOrder(): OperationServiceOrder
{
    $order = new OperationServiceOrder;
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);

    return $order;
}

it('prioriza la dirección capturada en la cotización aprobada', function (): void {
    $quote = new OperationQuoteGenerator(['supplier_address' => 'Av. Principal, Caracas']);
    $supplier = new Supplier(['ubicacion_principal' => 'Otra dirección']);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->setRelation('approvedOperationQuote', $quote);
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::address($order))->toBe('Av. Principal, Caracas');
});

it('usa la ubicación principal del proveedor jurídico', function (): void {
    $supplier = new Supplier(['ubicacion_principal' => 'Centro Profesional Colonial']);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::address($order))->toBe('Centro Profesional Colonial')
        ->and(OperationServiceOrderProviderSummary::addressOrDash($order))->toBe('Centro Profesional Colonial');
});

it('completa estado y ciudad cuando el jurídico no tiene ubicación principal', function (): void {
    $state = new State(['definition' => 'GUARICO']);
    $city = new City(['definition' => 'CALABOZO']);
    $supplier = new Supplier(['ubicacion_principal' => null]);
    $supplier->setRelation('state', $state);
    $supplier->setRelation('city', $city);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::address($order))->toBe('GUARICO — CALABOZO');
});

it('usa la ubicación principal del proveedor natural', function (): void {
    $doctor = new DoctorNurse(['ubicacion_principal' => 'Consulta pediátrica, La Castellana']);

    $order = providerSummaryOrder();
    $order->setRelation('doctorNurse', $doctor);

    expect(OperationServiceOrderProviderSummary::address($order))->toBe('Consulta pediátrica, La Castellana');
});

it('completa estado y ciudad textuales del proveedor natural', function (): void {
    $doctor = new DoctorNurse([
        'ubicacion_principal' => null,
        'state' => 'DISTRITO CAPITAL',
        'city' => 'CARACAS',
    ]);

    $order = providerSummaryOrder();
    $order->setRelation('doctorNurse', $doctor);

    expect(OperationServiceOrderProviderSummary::address($order))->toBe('DISTRITO CAPITAL — CARACAS');
});

it('devuelve nulo y guion cuando no hay proveedor con dirección', function (): void {
    $order = providerSummaryOrder();

    expect(OperationServiceOrderProviderSummary::address($order))->toBeNull()
        ->and(OperationServiceOrderProviderSummary::addressOrDash($order))->toBe('—');
});
