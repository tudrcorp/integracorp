<?php

declare(strict_types=1);

it('OperationServiceOrdersTable incluye acción modal de datos de pago con estilo iOS', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain("Action::make('registerPayment')")
        ->toContain('emptyStateHeading')
        ->toContain('supplierLabel')
        ->toContain('statusIcon')
        ->toContain('serviceTypeIcon')
        ->toContain('Registrar datos de pago')
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('aviso-btn-ios-success')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('paymentMethodOptions')
        ->toContain('hasRegisteredPaymentData')
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
        ->toContain('patientNameForOrder')
        ->toContain("->label('Nº caso')")
        ->toContain("->label('Nº orden')")
        ->toContain('operationCoordinationService.telemedicineCase')
        ->toContain('patientNameForOrder')
        ->toContain("TextColumn::make('currency')")
        ->toContain("TextColumn::make('associated_quote_pdf_path')");
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
