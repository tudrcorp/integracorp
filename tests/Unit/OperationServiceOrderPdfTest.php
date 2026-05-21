<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
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
        'operation_coordination_service_id' => 1,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
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
        ->and($html)->not->toContain('Trazabilidad');
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
        ->and($html)->toContain('Tasa BCV aplicada');
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
        ->and($html)->toContain('Paracetamol');
});
