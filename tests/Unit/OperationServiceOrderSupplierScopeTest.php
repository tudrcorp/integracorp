<?php

declare(strict_types=1);

it('persiste telemedicine_supplier_id y managed_by desde la coordinación al crear la orden', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php');

    expect($contents)
        ->toContain('OperationsSupplierScope::resolveTelemedicineSupplierIdFromCoordination($ownerRecord)')
        ->toContain('OperationsSupplierScope::managedByFromCoordination($ownerRecord)');
});

it('filtra órdenes de servicio por telemedicine_supplier_id en Operations', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'))
        ->toContain('OperationsSupplierScope::applyServiceOrderListScope($query)');

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_091502_add_telemedicine_supplier_id_and_managed_by_to_operation_service_orders_table.php'))
        ->toContain('telemedicine_supplier_id')
        ->toContain('managed_by');
});

it('expone helpers de coordinación y órdenes en OperationsSupplierScope', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/OperationsSupplierScope.php');

    expect($contents)
        ->toContain('resolveTelemedicineSupplierIdFromCoordination')
        ->toContain('managedByFromCoordination')
        ->toContain('applyServiceOrderListScope')
        ->toContain('serviceOrderQuery');
});
