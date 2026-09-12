<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Support\Operations\ServiceOrderBulkInvoice;

function ordenDeFacturaMasiva(int $id, array $attributes = []): OperationServiceOrder
{
    $orden = new OperationServiceOrder($attributes);
    $orden->id = $id;
    $orden->setRelation('supplier', null);
    $orden->setRelation('telemedicineSupplier', null);
    $orden->setRelation('doctorNurse', null);
    $orden->setRelation('approvedOperationQuote', null);
    $orden->setRelation('operationServiceOrderQuotes', collect());
    $orden->setRelation('operationCoordinationService', null);

    return $orden;
}

it('trata supplier_id y telemedicine_supplier_id como el mismo proveedor', function (): void {
    $juridico = ordenDeFacturaMasiva(1, ['supplier_id' => 44]);
    $telemedicina = ordenDeFacturaMasiva(2, ['telemedicine_supplier_id' => 44]);

    expect(ServiceOrderBulkInvoice::supplierKey($juridico))->toBe('supplier:44')
        ->and(ServiceOrderBulkInvoice::supplierKey($telemedicina))->toBe('supplier:44')
        ->and(ServiceOrderBulkInvoice::haveSameSupplier(collect([$juridico, $telemedicina])))->toBeTrue();
});

it('rechaza órdenes de proveedores distintos o sin proveedor', function (): void {
    $farmacia = ordenDeFacturaMasiva(1, ['supplier_id' => 10, 'order_number' => 'ORD-0101']);
    $instituto = ordenDeFacturaMasiva(2, ['supplier_id' => 99, 'order_number' => 'ORD-0102']);
    $sinProveedor = ordenDeFacturaMasiva(3, ['order_number' => 'ORD-0103']);

    expect(ServiceOrderBulkInvoice::haveSameSupplier(collect([$farmacia, $instituto])))->toBeFalse()
        ->and(ServiceOrderBulkInvoice::mismatchMessage(collect([$farmacia, $instituto])))
        ->toContain('mismo proveedor')
        ->and(ServiceOrderBulkInvoice::haveSameSupplier(collect([$farmacia, $sinProveedor])))->toBeFalse()
        ->and(ServiceOrderBulkInvoice::mismatchMessage(collect([$farmacia, $sinProveedor])))
        ->toContain('ORD-0103');
});

it('acepta el mismo proveedor no convenido aunque el nombre tenga espacios de más', function (): void {
    $una = ordenDeFacturaMasiva(1, ['supplier_external' => 'Farmacia  Canaan']);
    $otra = ordenDeFacturaMasiva(2, ['supplier_external' => 'FARMACIA CANAAN']);

    expect(ServiceOrderBulkInvoice::haveSameSupplier(collect([$una, $otra])))->toBeTrue();
});

it('prorratea el total de la factura según el monto cotizado de cada orden', function (): void {
    $chica = ordenDeFacturaMasiva(10, ['supplier_id' => 1]);
    $chica->setAttribute('operation_service_order_quotes_sum_total_amount_usd', 10);
    $grande = ordenDeFacturaMasiva(20, ['supplier_id' => 1]);
    $grande->setAttribute('operation_service_order_quotes_sum_total_amount_usd', 30);

    $reparto = ServiceOrderBulkInvoice::allocateAmounts(collect([$chica, $grande]), 80, null);

    expect($reparto[10]['usd'])->toBe(20.0)
        ->and($reparto[20]['usd'])->toBe(60.0)
        ->and($reparto[10]['ves'])->toBeNull()
        ->and(ServiceOrderBulkInvoice::quotedTotalUsd(collect([$chica, $grande])))->toBe(40.0);
});

it('reparte por igual cuando ninguna orden tiene monto cotizado', function (): void {
    $primera = ordenDeFacturaMasiva(1, ['supplier_id' => 1]);
    $segunda = ordenDeFacturaMasiva(2, ['supplier_id' => 1]);

    $reparto = ServiceOrderBulkInvoice::allocateAmounts(collect([$primera, $segunda]), 50, null);

    expect($reparto[1]['usd'])->toBe(25.0)
        ->and($reparto[2]['usd'])->toBe(25.0);
});

it('exige un monto para prorratear', function (): void {
    $orden = ordenDeFacturaMasiva(1, ['supplier_id' => 1]);

    expect(fn () => ServiceOrderBulkInvoice::allocateAmounts(collect([$orden]), null, null))
        ->toThrow(InvalidArgumentException::class, 'Indica al menos el monto facturado en US$ o en bolívares.');
});

it('pinta la tabla de seleccionados con la sumatoria', function (): void {
    $orden = ordenDeFacturaMasiva(8, [
        'supplier_id' => 1,
        'order_number' => 'ORD-0103',
        'service_type' => 'MEDICAMENTOS',
    ]);
    $orden->setAttribute('operation_service_order_quotes_sum_total_amount_usd', 13.44);
    $orden->setRelation('supplier', new Supplier(['name' => 'FARMACIA CANAAN, C.A.']));

    $html = ServiceOrderBulkInvoice::renderSelectedTable(collect([$orden]))->toHtml();

    expect($html)
        ->toContain('ORD-0103')
        ->toContain('FARMACIA CANAAN, C.A.')
        ->toContain('MEDICAMENTOS')
        ->toContain('1 orden seleccionada')
        ->toContain('Total US$ 13,44');
});

it('rechaza aplicar la factura masiva si el proveedor no es el mismo o falta el archivo', function (): void {
    $farmacia = ordenDeFacturaMasiva(1, ['supplier_id' => 10]);
    $otra = ordenDeFacturaMasiva(2, ['supplier_id' => 11]);

    expect(fn () => ServiceOrderBulkInvoice::apply(collect([$farmacia, $otra]), [], 'QA'))
        ->toThrow(InvalidArgumentException::class, 'mismo proveedor');

    expect(fn () => ServiceOrderBulkInvoice::apply(collect([$farmacia]), [
        'invoice_number' => '23154356',
        'invoice_file_path' => null,
        'invoice_amount_usd' => 10,
        'payable_supplier_name' => 'FARMACIA',
        'payable_supplier_rif' => 'J-1',
    ], 'QA'))->toThrow(InvalidArgumentException::class, 'Adjunta el archivo de la factura');
});

it('la tabla de órdenes expone la acción masiva de cargar factura', function (): void {
    $tabla = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');
    $accion = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Actions/ServiceOrderBulkInvoiceActions.php');

    expect($tabla)
        ->toContain('ServiceOrderBulkInvoiceActions::make()')
        ->and($accion)
        ->toContain("BulkAction::make('uploadInvoiceBulk')")
        ->toContain("'Cargar factura'")
        ->toContain('ServiceOrderBulkInvoice::haveSameSupplier')
        ->toContain('ServiceOrderBulkInvoice::renderSelectedTable')
        ->toContain('ServiceOrderBulkInvoice::apply')
        ->toContain("make('invoice_number')")
        ->toContain("make('invoice_file_path')")
        ->toContain("make('payable_supplier_name')")
        ->toContain("make('payable_supplier_rif')");
});
