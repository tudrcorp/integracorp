<?php

declare(strict_types=1);

it('OperationServiceOrdersTable conserva estilo iOS, prioridades y accesos a PDF', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain('emptyStateHeading')
        ->toContain('supplierLabel')
        ->toContain('statusIcon')
        ->toContain('serviceTypeIcon')
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('aviso-btn-ios-success')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('paymentMethodOptions')
        ->toContain('OperationServiceOrder $record')
        ->toContain('use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;')
        ->toContain('TelemedicinePriorityFilamentBadge::color')
        ->toContain('TelemedicinePriorityFilamentBadge::icon')
        ->toContain('TelemedicinePriorityFilamentBadge::recordRowClasses')
        ->toContain('private static function recordRowClasses')
        ->toContain("'FINALIZADO', 'CANCELADA', 'CANCELADO'")
        ->toContain("'CANCELADA' => 'danger'")
        ->toContain('border-gray-400 bg-gray-100/90')
        ->toContain('border-red-500 bg-red-50/90')
        ->toContain('OperationServiceOrderValidity::expireEligibleOrders')
        ->toContain('OperationServiceOrderListDisplay::patientFullName')
        ->toContain("->label('Nº caso')")
        ->toContain("->label('Orden servicio')")
        ->toContain("->label('Nº referencia')")
        ->toContain("TextColumn::make('operationCoordinationService.reference_number')")
        ->toContain('OperationServiceOrderListDisplay::serviceReferenceNumber')
        ->toContain("->label('Paciente')")
        ->toContain("->label('Estatus administrativo')")
        ->toContain("->label('U.N. específica')")
        ->toContain("->label('Monto cotizado')")
        ->toContain("Action::make('preview_order_pdf')")
        ->toContain("Action::make('preview_quote_pdf')")
        ->toContain('filament.operations.operation-service-orders.pdf-preview')
        ->toContain('operationCoordinationService.telemedicineCase')
        ->toContain("TextColumn::make('currency')")
        ->toContain("TextColumn::make('associated_quote_pdf_path')");
});

it('la tabla ya no ofrece las acciones de datos de pago ni de carga de soportes', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($src)
        ->not->toContain("Action::make('registerPayment')")
        ->and($src)->not->toContain("Action::make('upload_files')")
        ->and($src)->not->toContain('Registrar datos de pago')
        ->and($src)->not->toContain("->label('Cargar Soportes')")
        ->and($src)->not->toContain('hasRegisteredPaymentData');

    // La carga de factura y la vista previa de soportes ya cargados sí permanecen.
    expect($src)
        ->toContain("Action::make('uploadInvoice')")
        ->and($src)->toContain("Action::make('preview_files')");
});

it('oculta por defecto las columnas de pago y pdf en OperationServiceOrdersTable', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    foreach ([
        'currency',
        'tasa_bcv',
        'total_amount_usd',
        'total_amount_ves',
        'payment_method',
        'status_payment',
        'service_order_pdf_path',
        'associated_quote_pdf_path',
    ] as $column) {
        expect($src)->toContain("TextColumn::make('{$column}')");
        expect(preg_match(
            "/TextColumn::make\('{$column}'\).*?toggleable\(isToggledHiddenByDefault: true\)/s",
            $src
        ))->toBe(1);
    }
});
