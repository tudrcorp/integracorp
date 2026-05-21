<?php

declare(strict_types=1);

it('OperationCoordinationServicesTable define paso de cotización para ítems no cubiertos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Step::make('Cotización')")
        ->toContain('Obligatoria para ítems no cubiertos')
        ->toContain('nonCoveredSelectedManagementItemKeys')
        ->toContain('manage_service_non_covered_items_notice')
        ->toContain('manage_quote_costo_dolares')
        ->toContain('manage_quote_porcentaje_ganancia')
        ->toContain('manageQuoteSummaryPanel')
        ->toContain('Parámetros de cotización')
        ->toContain('createQuoteFromManageModal')
        ->toContain('persistManageQuote')
        ->toContain('buildManageQuoteItemsPayload')
        ->toContain('OperationQuoteGenerator::query()->create')
        ->toContain('OperationQuoteGenerator::STATUS_PENDING')
        ->toContain('quote_pdf_path')
        ->toContain('manageServiceNonCoveredItemsNotice');
});

it('OperationCoordinationServicesTable valida cotización antes de gestionar ítems no cubiertos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('$shouldCreateQuote = $nonCoveredKeys !== [] && $quoteType !== null')
        ->toContain('Indique un costo en dólares mayor a cero para los ítems no cubiertos.')
        ->toContain('Se registró la cotización para los ítems no cubiertos seleccionados.');
});
