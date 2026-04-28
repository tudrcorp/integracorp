<?php

declare(strict_types=1);

it('OperationServiceOrdersTable incluye acción modal de datos de pago con estilo iOS', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain("Action::make('registerPayment')")
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
        ->toContain('TelemedicinePriorityFilamentBadge::recordRowClasses');
});
