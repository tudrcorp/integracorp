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
        ->and($src)->toContain("\$actor = Auth::user()?->name ?? 'sistema';")
        ->and($src)->toContain("'invoice_uploaded_by' => \$actor,")
        ->and($src)->toContain("'invoice_uploaded_at' => now()")
        ->and($src)->toContain("'updated_by' => \$actor,")
        ->and($src)->toContain('Factura registrada con diferencia')
        ->and($src)->toContain("Action::make('previewInvoice')");
});

it('la migración de control y fecha de registro es aditiva e idempotente', function (): void {
    $src = (string) file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_09_10_160000_add_control_number_and_registration_date_to_operation_service_orders_table.php'
    );

    expect($src)
        ->toContain("hasColumn('operation_service_orders', 'invoice_control_number')")
        ->and($src)->toContain("hasColumn('operation_service_orders', 'invoice_registration_date')")
        ->and($src)->toContain("->string('invoice_control_number', 60)->nullable()->after('invoice_number')")
        ->and($src)->toContain("->date('invoice_registration_date')->nullable()->after('invoice_date')")
        ->and($src)->toContain("->index('invoice_control_number')")
        ->and($src)->not->toContain('dropIfExists')
        ->and($src)->not->toContain('migrate:fresh');
});

it('el modelo acepta control y fecha de registro, y castea la fecha', function (): void {
    $order = new OperationServiceOrder;

    expect($order->getFillable())
        ->toContain('invoice_control_number')
        ->toContain('invoice_registration_date');

    expect($order->getCasts())
        ->toHaveKey('invoice_registration_date', 'date')
        ->toHaveKey('invoice_date', 'date');

    $order->fill(['invoice_control_number' => '00-0012345']);

    expect($order->invoice_control_number)->toBe('00-0012345');
});

it('el formulario pide el N° de control y la fecha de registro con sus reglas', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("TextInput::make('invoice_control_number')")
        ->and($src)->toContain("->label('N° de control')")
        ->and($src)->toContain("DatePicker::make('invoice_registration_date')")
        ->and($src)->toContain("->label('Fecha de registro de la factura')")
        ->and($src)->toContain("->minDate(fn (Get \$get) => \$get('invoice_date') ?: null)")
        ->and($src)->toContain('->default(now()->startOfDay())')
        ->and($src)->toContain('->live(onBlur: true)');
});

it('los mensajes de validación del formulario de factura están en español', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("'required' => 'Indica el número de la factura.'")
        ->and($src)->toContain("'max' => 'El número de control no puede superar los 60 caracteres.'")
        ->and($src)->toContain("'required' => 'Indica la fecha en que registras la factura.'")
        ->and($src)->toContain("'after_or_equal' => 'La fecha de registro no puede ser anterior a la fecha de emisión de la factura.'")
        ->and($src)->toContain("'before_or_equal' => 'La fecha de registro no puede ser posterior a hoy.'")
        ->and($src)->toContain("'required_without' => 'Indica el monto en US\$ o, en su defecto, el monto en bolívares.'")
        ->and($src)->not->toContain('validation.required');
});

it('la factura se precarga y se guarda con control y fecha de registro', function (): void {
    $src = invoiceUploadTableSource();

    expect($src)
        ->toContain("'invoice_control_number' => \$record->invoice_control_number,")
        ->and($src)->toContain("'invoice_registration_date' => \$record->invoice_registration_date ?? now()->startOfDay(),")
        ->and($src)->toContain("'invoice_control_number' => \$controlNumber !== '' ? \$controlNumber : null,")
        ->and($src)->toContain("\$registrationDate = \$data['invoice_registration_date'] ?: now()->toDateString();")
        ->and($src)->toContain("'invoice_registration_date' => \$registrationDate,")
        ->and($src)->toContain("filled(\$record->invoice_control_number) ? ' (control '");
});
