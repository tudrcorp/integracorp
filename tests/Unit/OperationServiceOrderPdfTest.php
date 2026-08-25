<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Services\OperationServiceOrderQuotePdfService;

uses(Tests\TestCase::class);
use App\Services\OperationServiceOrderPdfService;

test('pdf filename sanitizes order number', function () {
    $order = new OperationServiceOrder;
    $order->order_number = 'OS-001/A';

    expect(OperationServiceOrderPdfService::filename($order))->toBe('orden-servicio-OS-001_A.pdf');
});

test('quote pdf filename sanitizes order number', function () {
    $order = new OperationServiceOrder;
    $order->order_number = 'OS-001/A';

    expect(OperationServiceOrderQuotePdfService::filename($order))->toBe('cotizacion-asociada-OS-001_A.pdf');
});

test('operation service order pdf blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T1',
        'description' => 'D',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 1,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Orden de servicio')
        ->and($html)->toContain('departamento de operaciones de Tu Doctor en Casa')
        ->and($html)->toContain('Servicio complementario')
        ->and($html)->toContain('Tipo de servicio')
        ->and($html)->toContain('ESPECIALISTA')
        ->and($html)->toContain('Dirección')
        ->and($html)->not->toContain('Trazabilidad')
        ->and($html)->not->toContain('Datos de la orden')
        ->and($html)->not->toContain('Montos y pago')
        ->and($html)->not->toContain('Coordinación y paciente');
});

test('operation service order pdf muestra datos de paciente y oculta secciones retiradas', function () {
    $coord = new App\Models\OperationCoordinationService([
        'patient' => 'IGNACIO ALEJANDRO RAMOS VASQUEZ',
        'ci_patient' => '18093303-2',
        'phone_holder' => '4241778952',
        'reference_number' => 'REF-31599',
        'address' => 'Urbanizacion Las Rosas',
        'contractor' => 'CORPORATIVO',
    ]);
    $coord->setRelation('state', null);
    $coord->setRelation('city', null);

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0062',
        'description' => 'Referencia de Pediatra',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 286,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', $coord);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Datos de paciente')
        ->and($html)->toContain('IGNACIO ALEJANDRO RAMOS VASQUEZ')
        ->and($html)->toContain('Tipo de servicio')
        ->and($html)->toContain('ESPECIALISTA')
        ->and($html)->toContain('Servicio complementario')
        ->and($html)->toContain('Dirección')
        ->and($html)->not->toContain('Datos de la orden')
        ->and($html)->not->toContain('Coordinación y paciente')
        ->and($html)->not->toContain('Montos y pago')
        ->and($html)->not->toContain('Método de pago')
        ->and($html)->not->toContain('Total USD');
});

test('operation service order pdf incluye la direccion del proveedor', function () {
    $supplier = new Supplier([
        'name' => 'CENTRO PROFESIONAL COLONIAL C.A',
        'ubicacion_principal' => 'Av. Bolivar, Calabozo',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0062',
        'description' => 'Referencia de Pediatra',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 286,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', $supplier);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Dirección')
        ->and($html)->toContain('Av. Bolivar, Calabozo')
        ->and($html)->not->toContain('Proveedor natural')
        ->and($html)->not->toContain('Proveedor jurídico');
});

it('la plantilla de OS no incluye datos de la orden ni montos y pago', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-pdf.blade.php');

    expect($src)
        ->toContain('Datos de paciente')
        ->toContain('Servicio complementario')
        ->toContain('Tipo de servicio')
        ->toContain('Dirección')
        ->toContain('OperationServiceOrderProviderSummary::addressOrDash')
        ->toContain('class="item-cat"')
        ->toContain('width:16%')
        ->toContain('max-width: 110px')
        ->toContain('margin-bottom: 10px')
        ->toContain('section-title--block')
        ->and($src)->not->toContain('Datos de la orden')
        ->and($src)->not->toContain('Coordinación y paciente')
        ->and($src)->not->toContain('Montos y pago')
        ->and($src)->not->toContain('Proveedor natural')
        ->and($src)->not->toContain('Proveedor jurídico')
        ->and($src)->not->toContain('Proveedor No Convenido');
});

test('operation service order quote blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T2',
        'description' => 'D',
        'operation_coordination_service_id' => 2,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('telemedicinePriority', null);

    $html = view('documents.operation-service-order-quote-pdf', [
        'order' => $order,
        'quoteData' => [
            'service_label' => 'Laboratorio',
            'price_usd' => 10,
            'price_ves' => 1000,
            'bcv_rate' => 100,
        ],
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Cotización asociada')
        ->and($html)->toContain('Precio cotizado (USD)')
        ->and($html)->not->toContain('Tasa BCV aplicada')
        ->and($html)->not->toContain('Precio cotizado (Bs.)');
});

test('operation service order medication quote blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T3',
        'description' => 'D',
        'operation_coordination_service_id' => 3,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('telemedicinePriority', null);

    $html = view('documents.operation-service-order-medication-quote-pdf', [
        'order' => $order,
        'quoteMeta' => [
            'quote_number' => 'COT-T3-01',
            'supplier_name' => 'Proveedor Demo',
            'bcv_rate' => 100,
            'total_amount_usd' => 10,
            'total_amount_ves' => 1000,
        ],
        'items' => [
            ['item_name' => 'Paracetamol', 'quantity' => 1, 'unit_amount_usd' => 10, 'line_total_usd' => 10],
        ],
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Cotización de medicamentos')
        ->and($html)->toContain('Ítems cotizados')
        ->and($html)->toContain('Paracetamol')
        ->and($html)->not->toContain('Total Bs.')
        ->and($html)->not->toContain('Tasa BCV');
});
