<?php

declare(strict_types=1);

it('OperationServiceOrdersTable incluye flujo de cotización de medicamentos por proveedor', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain("Action::make('manageMedicationQuotes')")
        ->toContain('Cotizar medicamentos')
        ->toContain('split_by_supplier')
        ->toContain('quote_groups')
        ->toContain('single_quote_item_ids')
        ->toContain('persistMedicationQuote')
        ->toContain('OperationServiceOrderMedicationQuotePdfService::make')
        ->toContain("Action::make('viewMedicationQuotes')")
        ->toContain('renderMedicationQuotesPreview')
        ->toContain('operation_service_order_quotes_count')
        ->toContain('approvedOperationQuote')
        ->toContain('Código cotización');
});
