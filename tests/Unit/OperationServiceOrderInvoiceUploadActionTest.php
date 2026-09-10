<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderListDisplay;

function invoiceUploadTableSource(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'
    );
}

it('la migración de factura es aditiva e idempotente', function (): void {
    $src = (string) file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_09_10_120000_add_invoice_fields_to_operation_service_orders_table.php'
    );

    foreach ([
        'invoice_number',
        'invoice_date',
        'invoice_amount_usd',
        'invoice_amount_ves',
        'invoice_file_path',
        'invoice_uploaded_by',
        'invoice_uploaded_at',
    ] as $column) {
        expect($src)->toContain("hasColumn('operation_service_orders', '".$column."')");
    }

    expect($src)
        ->toContain("->decimal('invoice_amount_usd', 15, 4)->nullable()")
        ->and($src)->toContain("->date('invoice_date')->nullable()")
        ->and($src)->not->toContain('dropIfExists')
        ->and($src)->not->toContain('migrate:fresh');
});

it('el modelo declara los campos de factura como fillable y con sus casts', function (): void {
    $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationServiceOrder.php');

    expect($src)
        ->toContain("'invoice_number',")
        ->and($src)->toContain("'invoice_file_path',")
        ->and($src)->toContain("'invoice_uploaded_at',")
        ->and($src)->toContain("'invoice_date' => 'date',")
        ->and($src)->toContain("'invoice_uploaded_at' => 'datetime',");
});

it('FACTURADO es una opción del estatus administrativo y se pinta en verde', function (): void {
    expect(OperationServiceOrderListDisplay::administrativeStatusOptions())
        ->toHaveKey(OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED, 'Facturado')
        ->toHaveKey(OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_PENDING, 'Pendiente');

    expect(OperationServiceOrderListDisplay::administrativeStatusColor('FACTURADO'))->toBe('success')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor('facturado'))->toBe('success')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor('PENDIENTE'))->toBe('danger')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor(null))->toBe('danger')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor('OTRO'))->toBe('gray');

    expect(OperationServiceOrderListDisplay::administrativeStatusIcon('FACTURADO'))->toBe('heroicon-m-check-badge')
        ->and(OperationServiceOrderListDisplay::administrativeStatusIcon('PENDIENTE'))->toBe('heroicon-m-clipboard-document-check');
});

it('hasInvoice detecta la factura por número o por archivo', function (): void {
    expect(OperationServiceOrderListDisplay::hasInvoice(new OperationServiceOrder))->toBeFalse()
        ->and(OperationServiceOrderListDisplay::hasInvoice(new OperationServiceOrder(['invoice_number' => '  '])))->toBeFalse()
        ->and(OperationServiceOrderListDisplay::hasInvoice(new OperationServiceOrder(['invoice_number' => '00012345'])))->toBeTrue()
        ->and(OperationServiceOrderListDisplay::hasInvoice(new OperationServiceOrder(['invoice_file_path' => 'a/b.pdf'])))->toBeTrue();
});

it('la tabla expone la acción de carga de factura con los datos del proveedor y la cotización', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("Action::make('uploadInvoice')")
        ->and($src)->toContain("'Cargar factura'")
        ->and($src)->toContain("'Actualizar factura'")
        ->and($src)->toContain('renderInvoiceContextPreview')
        ->and($src)->toContain('invoiceSupplier')
        ->and($src)->toContain("'Código de cotización', \$quoteCode")
        ->and($src)->toContain("'Monto cotizado', \$quoteAmount")
        ->and($src)->toContain("'Proveedor', \$supplierName")
        ->and($src)->toContain('OperationServiceOrderListDisplay::quoteCodeLabel($record->approvedOperationQuote?->id)')
        ->and($src)->toContain('OperationServiceOrderListDisplay::quoteAmountLabel($record)');
});

it('el formulario de factura valida número, fecha, monto y archivo', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("TextInput::make('invoice_number')")
        ->and($src)->toContain("DatePicker::make('invoice_date')")
        ->and($src)->toContain("TextInput::make('invoice_amount_usd')")
        ->and($src)->toContain("TextInput::make('invoice_amount_ves')")
        ->and($src)->toContain("FileUpload::make('invoice_file_path')")
        ->and($src)->toContain("->requiredWithout('invoice_amount_ves')")
        ->and($src)->toContain("->requiredWithout('invoice_amount_usd')")
        ->and($src)->toContain("->directory('operation-service-orders/invoices')")
        ->and($src)->toContain("->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])")
        ->and($src)->toContain('->maxDate(now())');
});

it('al guardar la factura la orden queda en estatus FACTURADO y con auditoría', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("'administrative_status' => OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED")
        ->and($src)->toContain("'invoice_uploaded_by' => Auth::user()?->name ?? 'sistema'")
        ->and($src)->toContain("'invoice_uploaded_at' => now()")
        ->and($src)->toContain("'updated_by' => Auth::user()?->name ?? 'sistema'")
        ->and($src)->toContain('Factura registrada con diferencia')
        ->and($src)->toContain("Action::make('previewInvoice')");
});
