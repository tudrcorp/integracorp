<?php

declare(strict_types=1);

it('incluye migración que añade status_payment a operation_service_orders', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_08_03_140205_add_status_payment_to_operation_service_orders_table.php';
    $src = file_get_contents($path);

    expect(is_string($src))->toBeTrue()
        ->and($src)->toContain("hasColumn('operation_service_orders', 'status_payment')")
        ->and($src)->toContain("->string('status_payment')->nullable()")
        ->and($src)->toContain("'PAGADO'")
        ->and($src)->toContain("'PENDIENTE'");
});

it('OperationServiceOrdersTable busca status_payment solo si la columna existe en código', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($src)->toContain("TextColumn::make('status_payment')")
        ->and($src)->toContain('->searchable()');
});
