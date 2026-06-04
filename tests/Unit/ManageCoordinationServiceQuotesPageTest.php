<?php

declare(strict_types=1);

it('registra la página dedicada de gestión de cotizaciones en el recurso', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/OperationCoordinationServiceResource.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ManageCoordinationServiceQuotes.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    expect($resource)
        ->toContain('ManageCoordinationServiceQuotes::route')
        ->toContain('manage-quotes');

    expect($page)
        ->toContain('CoordinationServiceQuoteManager::formDefaults')
        ->toContain('CoordinationServiceQuoteManager::save')
        ->toContain('OperationServiceOrderResource::getUrl(\'view\'')
        ->toContain('fi-coordination-manage-quotes-page');

    expect($table)
        ->toContain('ManageCoordinationServiceQuotes::getUrl')
        ->not->toContain('modalHeading(\'Gestionar cotizaciones del servicio\')');
});

it('centraliza la lógica de cotizaciones en CoordinationServiceQuoteManager', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php'))
        ->toContain('final class CoordinationServiceQuoteManager')
        ->toContain('function save(')
        ->toContain('return $createdOrderId > 0 ? $createdOrderId : 0')
        ->toContain('function createServiceOrderFromApprovedQuote')
        ->toContain('selected_pending_quote_ids')
        ->toContain('syncQuoteStatusesFromPendingSelection')
        ->toContain('pendingQuoteApprovalOptions');
});

it('ofrece selección múltiple de cotizaciones pendientes en el formulario', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php'))
        ->toContain('CheckboxList::make(\'selected_pending_quote_ids\')')
        ->toContain('Selección para aprobar')
        ->toContain('bulkToggleable');
});
