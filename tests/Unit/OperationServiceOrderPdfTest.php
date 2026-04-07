<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;

uses(Tests\TestCase::class);
use App\Services\OperationServiceOrderPdfService;

test('pdf filename sanitizes order number', function () {
    $order = new OperationServiceOrder;
    $order->order_number = 'OS-001/A';

    expect(OperationServiceOrderPdfService::filename($order))->toBe('orden-servicio-OS-001_A.pdf');
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
