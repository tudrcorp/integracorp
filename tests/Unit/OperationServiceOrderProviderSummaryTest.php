<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\DoctorNurse;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\State;
use App\Models\Supplier;
use App\Support\Operations\OperationServiceOrderProviderSummary;

function providerSummaryOrder(): OperationServiceOrder
{
    $order = new OperationServiceOrder;
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('medicalAppointment', null);

    return $order;
}

it('resuelve el nombre del proveedor jurídico', function (): void {
    $supplier = new Supplier(['name' => 'CENTRO MÉDICO DOCENTE LAS ACACIAS']);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::name($order))->toBe('CENTRO MÉDICO DOCENTE LAS ACACIAS')
        ->and(OperationServiceOrderProviderSummary::nameOrDash($order))->toBe('CENTRO MÉDICO DOCENTE LAS ACACIAS');
});

it('resuelve el nombre del proveedor natural cuando no hay jurídico', function (): void {
    $doctor = new DoctorNurse(['name' => 'Dra. Ana Pérez']);

    $order = providerSummaryOrder();
    $order->setRelation('doctorNurse', $doctor);

    expect(OperationServiceOrderProviderSummary::name($order))->toBe('Dra. Ana Pérez');
});

it('usa el proveedor no convenido cuando no hay jurídico ni natural', function (): void {
    $order = providerSummaryOrder();
    $order->supplier_external = 'Clínica Externa Valera';

    expect(OperationServiceOrderProviderSummary::name($order))->toBe('Clínica Externa Valera');
});

it('devuelve nulo y guion cuando no hay nombre de proveedor', function (): void {
    $order = providerSummaryOrder();

    expect(OperationServiceOrderProviderSummary::name($order))->toBeNull()
        ->and(OperationServiceOrderProviderSummary::nameOrDash($order))->toBe('—');
});

it('prioriza el jurídico sobre el natural y el no convenido', function (): void {
    $supplier = new Supplier(['name' => 'Clínica Convenida']);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);
    $doctor = new DoctorNurse(['name' => 'Dr. Natural']);

    $order = providerSummaryOrder();
    $order->supplier_external = 'Proveedor Externo';
    $order->setRelation('supplier', $supplier);
    $order->setRelation('doctorNurse', $doctor);

    expect(OperationServiceOrderProviderSummary::name($order))->toBe('Clínica Convenida');
});

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

it('resuelve el teléfono del proveedor jurídico', function (): void {
    $supplier = new Supplier([
        'name' => 'Clínica Convenida',
        'personal_phone' => '04141234567',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::phone($order))->toBe('04141234567')
        ->and(OperationServiceOrderProviderSummary::phoneOrDash($order))->toBe('04141234567');
});

it('resuelve el teléfono del proveedor natural cuando no hay jurídico', function (): void {
    $doctor = new DoctorNurse([
        'name' => 'Dra. Ana Pérez',
        'local_phone' => '02125551234',
    ]);

    $order = providerSummaryOrder();
    $order->setRelation('doctorNurse', $doctor);

    expect(OperationServiceOrderProviderSummary::phone($order))->toBe('02125551234');
});

it('usa el teléfono de la cita cuando la ficha no lo tiene', function (): void {
    $supplier = new Supplier([
        'name' => 'Clínica Sin Teléfono',
        'personal_phone' => null,
        'local_phone' => null,
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $appointment = new App\Models\OperationMedicalAppointment([
        'supplier_notify_phone' => '04149839900',
    ]);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);
    $order->setRelation('medicalAppointment', $appointment);

    expect(OperationServiceOrderProviderSummary::phone($order))->toBe('04149839900');
});

it('devuelve nulo y guion cuando no hay teléfono del proveedor', function (): void {
    $order = providerSummaryOrder();
    $order->setRelation('medicalAppointment', null);

    expect(OperationServiceOrderProviderSummary::phone($order))->toBeNull()
        ->and(OperationServiceOrderProviderSummary::phoneOrDash($order))->toBe('—');
});

it('resuelve la CI/RIF del proveedor jurídico de la orden', function (): void {
    $supplier = new Supplier([
        'name' => 'Laboratorio Central',
        'rif' => 'j-12345678-9',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->setRelation('supplier', $supplier);

    expect(OperationServiceOrderProviderSummary::rif($order))->toBe('J-12345678-9');
});

it('asocia el proveedor de la orden a cada ítem clínico de la gestión', function (): void {
    $supplier = new Supplier([
        'name' => 'Laboratorio Central',
        'rif' => 'J-00112233-4',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = providerSummaryOrder();
    $order->id = 20;
    $order->status = 'EN GESTION';
    $order->setRelation('supplier', $supplier);
    $order->setRelation('operationServiceOrderItems', collect([
        new OperationServiceOrderItem([
            'category' => 'LABORATORIOS',
            'item_name' => 'CREATININA',
        ]),
    ]));

    $coordination = new OperationCoordinationService;
    $coordination->setRelation('operationServiceOrders', collect([$order]));
    $coordination->setRelation('operationQuoteGenerators', collect());

    $lookup = OperationServiceOrderProviderSummary::managementProvidersByClinicalLookup($coordination);

    expect($lookup['LABORATORIOS|CREATININA'])->toMatchArray([
        'name' => 'Laboratorio Central',
        'rif' => 'J-00112233-4',
        'order_id' => 20,
    ])->and(OperationServiceOrderProviderSummary::providerForClinicalItem(
        $lookup,
        'Laboratorio',
        'CREATININA',
        'lab:15',
    ))->toMatchArray([
        'name' => 'Laboratorio Central',
        'rif' => 'J-00112233-4',
        'order_id' => 20,
    ]);
});

it('usa el proveedor de la cotización cuando el ítem aún no tiene orden', function (): void {
    $quote = new OperationQuoteGenerator([
        'status' => OperationQuoteGenerator::STATUS_PENDING,
        'items' => [
            [
                'key' => 'lab:15',
                'category' => 'Laboratorio',
                'label' => 'GLICEMIA',
            ],
        ],
    ]);
    $quote->id = 8;
    $quote->setRelation('supplier', new Supplier([
        'name' => 'Diagnóstico del Este',
        'rif' => 'J-99887766-5',
    ]));

    $coordination = new OperationCoordinationService;
    $coordination->setRelation('operationServiceOrders', collect());
    $coordination->setRelation('operationQuoteGenerators', collect([$quote]));

    $lookup = OperationServiceOrderProviderSummary::managementProvidersByClinicalLookup($coordination);

    expect(OperationServiceOrderProviderSummary::providerForClinicalItem(
        $lookup,
        'Laboratorio',
        'GLICEMIA',
        'lab:15',
    ))->toMatchArray([
        'name' => 'Diagnóstico del Este',
        'rif' => 'J-99887766-5',
        'quote_id' => 8,
    ]);
});

it('omite órdenes anuladas al resolver el proveedor del ítem clínico', function (): void {
    $cancelledSupplier = new Supplier(['name' => 'Clínica Anulada', 'rif' => 'J-11111111-1']);
    $cancelledSupplier->setRelation('state', null);
    $cancelledSupplier->setRelation('city', null);

    $cancelled = providerSummaryOrder();
    $cancelled->id = 1;
    $cancelled->status = 'CANCELADA';
    $cancelled->setRelation('supplier', $cancelledSupplier);
    $cancelled->setRelation('operationServiceOrderItems', collect([
        new OperationServiceOrderItem([
            'category' => 'LABORATORIOS',
            'item_name' => 'UROANALISIS',
        ]),
    ]));

    $activeSupplier = new Supplier(['name' => 'Clínica Vigente', 'rif' => 'J-22222222-2']);
    $activeSupplier->setRelation('state', null);
    $activeSupplier->setRelation('city', null);

    $active = providerSummaryOrder();
    $active->id = 2;
    $active->status = 'EN GESTION';
    $active->setRelation('supplier', $activeSupplier);
    $active->setRelation('operationServiceOrderItems', collect([
        new OperationServiceOrderItem([
            'category' => 'LABORATORIOS',
            'item_name' => 'UROANALISIS',
        ]),
    ]));

    $coordination = new OperationCoordinationService;
    $coordination->setRelation('operationServiceOrders', collect([$cancelled, $active]));
    $coordination->setRelation('operationQuoteGenerators', collect());

    expect(OperationServiceOrderProviderSummary::providerForClinicalItem(
        OperationServiceOrderProviderSummary::managementProvidersByClinicalLookup($coordination),
        'Laboratorio',
        'UROANALISIS',
        'lab:4',
    ))->toMatchArray([
        'name' => 'Clínica Vigente',
        'rif' => 'J-22222222-2',
    ]);
});
