<?php

declare(strict_types=1);

use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderListDisplay;
use App\Support\Operations\ServiceOrderAccountsPayableRegistrar;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe sobre una orden y su cuenta por pagar: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function receiptTableSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');
}

it('la factura pide N° de factura o N° de recibo: ninguno es obligatorio por sí solo, pero al menos uno sí', function (): void {
    $src = receiptTableSource();

    expect($src)
        ->toContain("TextInput::make('invoice_receipt_number')")
        ->toContain("->label('N° de recibo')")
        ->toContain("->requiredWithout('invoice_receipt_number')")
        ->toContain("->requiredWithout('invoice_number')")
        ->toContain("'required_without' => 'Indica el N° de recibo o el N° de factura: al menos uno.'")
        ->toContain("if (\$invoiceNumber === '' && \$receiptNumber === '') {")
        ->toContain("'invoice_receipt_number' => \$record->invoice_receipt_number,")
        ->not->toMatch("/TextInput::make\\('invoice_number'\\)\\s*->label\\('N° de factura'\\)\\s*->prefixIcon\\('heroicon-m-hashtag'\\)\\s*->placeholder\\('Ej. 00012345'\\)\\s*->required\\(\\)/");
});

it('la etiqueta del documento muestra factura, recibo o ambos', function (): void {
    $order = new OperationServiceOrder(['invoice_number' => '00012345']);
    $receipt = new OperationServiceOrder(['invoice_receipt_number' => 'R-45']);
    $both = new OperationAccountsPayable(['invoice_number' => '00012345', 'invoice_receipt_number' => 'R-45']);

    expect($order->invoiceDocumentLabel())->toBe('Factura 00012345')
        ->and($receipt->invoiceDocumentLabel())->toBe('Recibo R-45')
        ->and($both->invoiceDocumentLabel())->toBe('Factura 00012345 · Recibo R-45')
        ->and((new OperationServiceOrder)->invoiceDocumentLabel())->toBe('Factura cargada')
        ->and(OperationServiceOrderListDisplay::hasInvoice($receipt))->toBeTrue()
        ->and(OperationServiceOrderListDisplay::hasInvoice(new OperationServiceOrder))->toBeFalse();
});

it('una factura con solo N° de recibo se guarda en la orden y crea su cuenta por pagar sin número de factura', function (): void {
    $order = OperationServiceOrder::query()->whereNotNull('supplier_id')->first();

    if (! $order instanceof OperationServiceOrder) {
        test()->markTestSkipped('No hay órdenes de servicio con proveedor en la base.');
    }

    OperationAccountsPayable::query()->where('operation_service_order_id', $order->getKey())->delete();
    $order->update(['invoice_number' => null, 'invoice_receipt_number' => 'R-PEST-1']);

    $result = ServiceOrderAccountsPayableRegistrar::register($order, [
        'invoice_number' => null,
        'invoice_receipt_number' => 'R-PEST-1',
        'invoice_control_number' => null,
        'invoice_date' => now()->toDateString(),
        'invoice_registration_date' => now()->toDateString(),
        'invoice_amount_usd' => 25.0,
        'invoice_amount_ves' => null,
        'invoice_file_path' => 'operations/invoices/pest.pdf',
        'supplier_name' => 'PROVEEDOR PRUEBA',
        'supplier_rif' => 'J-000000000',
        'business_unit_id' => null,
    ], 'QA');

    $payable = $result['payable']->fresh();

    expect($result['created'])->toBeTrue()
        ->and($payable->invoice_number)->toBeNull()
        ->and($payable->invoice_receipt_number)->toBe('R-PEST-1')
        ->and($payable->invoiceDocumentLabel())->toBe('Recibo R-PEST-1')
        ->and($order->fresh()->invoice_receipt_number)->toBe('R-PEST-1');
});

it('el formulario manual de Cuentas por pagar también acepta factura o recibo', function (): void {
    $form = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationAccountsPayables/Schemas/OperationAccountsPayableForm.php');

    expect($form)
        ->toContain("TextInput::make('invoice_receipt_number')")
        ->toContain("->requiredWithout('invoice_receipt_number')")
        ->toContain("->requiredWithout('invoice_number')");
});
