<?php

declare(strict_types=1);

it('incluye migración que añade administrative_status a operation_service_orders', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_09_09_233500_add_administrative_status_to_operation_service_orders_table.php';
    $src = file_get_contents($path);

    expect(is_string($src))->toBeTrue()
        ->and($src)->toContain("hasColumn('operation_service_orders', 'administrative_status')")
        ->and($src)->toContain("->string('administrative_status')->default('PENDIENTE')")
        ->and($src)->toContain("'PENDIENTE'");
});

it('el modelo y el alta de órdenes fijan PENDIENTE como estatus administrativo', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationServiceOrder.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php');

    expect($model)
        ->toContain("'administrative_status'")
        ->and($model)->toContain("\$order->administrative_status = 'PENDIENTE'")
        ->and($controller)->toContain("'administrative_status' => \$data['administrative_status'] ?? 'PENDIENTE'");
});

it('OperationServiceOrdersTable agrupa paciente y muestra estatus administrativo en rojo', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($src)
        ->toContain("TextColumn::make('patient_identity')")
        ->and($src)->toContain("->label('Paciente')")
        ->and($src)->toContain('OperationServiceOrderListDisplay::patientFullName')
        ->and($src)->toContain('OperationServiceOrderListDisplay::patientDocumentLabel')
        ->and($src)->toContain("TextColumn::make('administrative_status')")
        ->and($src)->toContain("->label('Estatus administrativo')")
        ->and($src)->toContain('OperationServiceOrderListDisplay::administrativeStatusColor')
        ->and($src)->toContain("->label('U.N. específica')")
        ->and($src)->toContain("->label('Monto cotizado')")
        ->and($src)->toContain("SelectFilter::make('administrative_status')")
        ->and($src)->toContain("->withSum('operationServiceOrderQuotes', 'total_amount_usd')");
});

it('OperationServiceOrderInfolist muestra el estatus administrativo', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php');

    expect($src)
        ->toContain("TextEntry::make('administrative_status')")
        ->and($src)->toContain("->label('Estatus administrativo')")
        ->and($src)->toContain('OperationServiceOrderListDisplay::administrativeStatusColor');
});
