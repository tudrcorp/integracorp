<?php

declare(strict_types=1);

it('el formulario de gestión de ítems define paso de cotización para ítems no cubiertos', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    expect($form)
        ->toContain("Step::make('Cotización')")
        ->toContain('Obligatoria para ítems no cubiertos')
        ->toContain('nonCoveredSelectedManagementItemKeys')
        ->toContain('manage_service_non_covered_items_notice')
        ->toContain('manage_quote_line_items')
        ->toContain('unit_price_usd')
        ->toContain('unit_price_ves')
        ->toContain('buildManageQuoteLineItemsDefault')
        ->toContain('syncManageQuoteAggregates')
        ->toContain('manageQuoteSummaryPanel')
        ->toContain('Precios unitarios por ítem')
        ->toContain('manageServiceNonCoveredItemsNotice');
});

it('CoordinationServiceItemsManager valida cotización antes de gestionar ítems no cubiertos', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    expect($manager)
        ->toContain('$shouldCreateQuote = $nonCoveredKeys !== [] && $quoteType !== null')
        ->toContain('Indique el precio unitario en dólares (mayor a cero) para cada ítem no cubierto seleccionado.')
        ->toContain('Se registró la cotización para los ítems no cubiertos seleccionados.')
        ->toContain('persistManageQuote')
        ->toContain('buildManageQuoteItemsPayload')
        ->toContain('OperationQuoteGenerator::query()->create')
        ->toContain('OperationQuoteGenerator::STATUS_PENDING')
        ->toContain('quote_pdf_path');
});

it('manageQuoteSubtotalFromLineItems suma precios unitarios en USD', function (): void {
    $reflection = new ReflectionClass(\App\Support\Operations\CoordinationServiceItemsManager::class);
    $method = $reflection->getMethod('manageQuoteSubtotalFromLineItems');
    $method->setAccessible(true);

    $subtotal = $method->invoke(null, [
        ['key' => 'medication:1', 'unit_price_usd' => 10.5],
        ['key' => 'lab:2', 'unit_price_usd' => 25],
    ]);

    expect($subtotal)->toBe(35.5);
});

it('manageQuoteTotal aplica porcentaje de ganancia sobre el subtotal', function (): void {
    $reflection = new ReflectionClass(\App\Support\Operations\CoordinationServiceItemsManager::class);
    $method = $reflection->getMethod('manageQuoteTotal');
    $method->setAccessible(true);

    $total = $method->invoke(null, 100.0, 15.0);

    expect($total)->toBe(115.0);
});
