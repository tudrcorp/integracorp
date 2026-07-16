<?php

declare(strict_types=1);

it('el formulario de gestión de ítems define paso de cotización para ítems no cubiertos', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    expect($form)
        ->toContain("Step::make('Cotización')")
        ->toContain('Obligatoria para ítems no cubiertos')
        ->toContain('shouldShowManageQuoteStep')
        ->toContain('manage_service_non_covered_items_notice')
        ->toContain('manage_quote_line_items')
        ->toContain('unit_price_usd')
        ->toContain('unit_price_ves')
        ->toContain('buildManageQuoteLineItemsDefault')
        ->toContain('syncManageQuoteAggregates')
        ->toContain('manageQuoteSummaryPanel')
        ->toContain('Precios unitarios por ítem')
        ->toContain('manage_quote_supplier_id')
        ->toContain('manage_quote_supplier_address')
        ->toContain('manage_quote_observations')
        ->toContain('Observaciones de la cotización')
        ->toContain('resolveManageQuoteSupplierAddress')
        ->toContain('manageServiceNonCoveredItemsNotice')
        ->toContain('ManageQuoteSupplierCreator::configureSelect');
});

it('permite crear un proveedor nuevo desde parámetros de cotización y seleccionarlo', function (): void {
    $creator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/ManageQuoteSupplierCreator.php');
    $editForm = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteEditForm.php');

    expect($creator)
        ->toContain('createOptionForm')
        ->toContain('createOptionUsing')
        ->toContain('createOptionAction')
        ->toContain('Crear proveedor')
        ->toContain('Crear y seleccionar')
        ->toContain('Supplier::query()->create')
        ->toContain('return (int) $supplier->getKey()');

    expect($editForm)
        ->toContain('ManageQuoteSupplierCreator::configureSelect');
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
        ->toContain('quote_pdf_path')
        ->toContain('manage_quote_supplier_id')
        ->toContain('supplier_address')
        ->toContain('manage_quote_observations')
        ->toContain("'observations'")
        ->toContain('Seleccione el proveedor en los parámetros de cotización.');
});

it('resolveManageQuoteSupplierAddress devuelve null sin proveedor', function (): void {
    expect(\App\Support\Operations\CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress(null))->toBeNull();
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
