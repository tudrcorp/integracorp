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
        ->toContain('fi-coordination-manage-quotes-page')
        ->toContain('editPendingQuoteAction')
        ->toContain('focusQuoteFromQueryString')
        ->toContain('selectAllPendingQuotes')
        ->toContain('updatedDataSelectedPendingQuoteIds');

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
        ->toContain('finalizeClinicalItemsForPrivateCareQuote')
        ->toContain('quoteStatusUpdateIsLocked')
        ->toContain('STATUS_PRIVATE_CARE')
        ->toContain('selected_pending_quote_ids')
        ->toContain('syncQuoteStatusesFromPendingSelection')
        ->toContain('syncQuoteStatusesFromFormData')
        ->toContain('pendingQuotesForApproval')
        ->toContain('updatePendingQuote')
        ->toContain('edit_quote_costo_dolares')
        ->toContain('mergeOrderDataWithQuoteProvider')
        ->toContain('renderQuoteSupplierPreviewBlock')
        ->toContain('pendingQuoteApprovalOptions')
        ->toContain('quoteLinksByClinicalItemKey')
        ->toContain('formatCoordinationQuoteNumber')
        ->toContain('approvalUrlForQuote');
});

it('OperationQuoteGenerator ofrece estatus Atencion Particular sin orden de servicio', function (): void {
    expect(\App\Models\OperationQuoteGenerator::statusOptions())
        ->toHaveKey(\App\Models\OperationQuoteGenerator::STATUS_PRIVATE_CARE, 'Atención Particular')
        ->and(\App\Models\OperationQuoteGenerator::isTerminalStatus(\App\Models\OperationQuoteGenerator::STATUS_PRIVATE_CARE))
        ->toBeTrue()
        ->and(\App\Models\OperationQuoteGenerator::isTerminalStatus(\App\Models\OperationQuoteGenerator::STATUS_PENDING))
        ->toBeFalse();

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php'))
        ->toContain('STATUS_PRIVATE_CARE')
        ->toContain('Hidden::make(\'status_locked\')')
        ->toContain('status_locked')
        ->toContain('El estatus de esta cotización ya fue definido y no puede modificarse.')
        ->toContain('Atención particular: finaliza la cotización y los ítems sin generar orden de servicio.');
});

it('quoteStatusUpdateIsLocked bloquea cotizaciones con estatus terminal', function (): void {
    $approved = new \App\Models\OperationQuoteGenerator([
        'status' => \App\Models\OperationQuoteGenerator::STATUS_APPROVED,
    ]);

    $rejected = new \App\Models\OperationQuoteGenerator([
        'status' => \App\Models\OperationQuoteGenerator::STATUS_REJECTED,
    ]);

    $privateCare = new \App\Models\OperationQuoteGenerator([
        'status' => \App\Models\OperationQuoteGenerator::STATUS_PRIVATE_CARE,
    ]);

    $pending = new \App\Models\OperationQuoteGenerator([
        'status' => \App\Models\OperationQuoteGenerator::STATUS_PENDING,
    ]);

    expect(\App\Support\Operations\CoordinationServiceQuoteManager::quoteStatusUpdateIsLocked($approved))->toBeTrue()
        ->and(\App\Support\Operations\CoordinationServiceQuoteManager::quoteStatusUpdateIsLocked($rejected))->toBeTrue()
        ->and(\App\Support\Operations\CoordinationServiceQuoteManager::quoteStatusUpdateIsLocked($privateCare))->toBeTrue()
        ->and(\App\Support\Operations\CoordinationServiceQuoteManager::quoteStatusUpdateIsLocked($pending))->toBeFalse();
});

it('ofrece selección múltiple y edición modal de cotizaciones pendientes', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php');
    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/partials/pending-quotes-selection.blade.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/resources/operation-coordination-services/pages/manage-coordination-service-quotes.blade.php');

    expect($form)
        ->toContain('Hidden::make(\'selected_pending_quote_ids\')')
        ->toContain('Selección para aprobar')
        ->toContain('pending-quotes-selection')
        ->not->toContain('OperationServiceOrderProviderFormFields::selectionComponents()')
        ->not->toContain('register_unregistered_provider');

    expect($partial)
        ->toContain('Seleccionar todos')
        ->toContain('mountAction(\'editPendingQuote\'')
        ->toContain('Editar');

    expect($blade)->toContain('filament-actions::modals');
});

it('define el formulario y la acción modal para editar cotizaciones pendientes', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteEditForm.php'))
        ->toContain('edit_quote_supplier_id')
        ->toContain('edit_quote_line_items')
        ->toContain('edit_quote_costo_dolares')
        ->toContain('Costo base (USD)')
        ->toContain('edit_quote_observations');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteEditAction.php'))
        ->toContain('editPendingQuote')
        ->toContain('CoordinationServiceQuoteEditForm::defaults')
        ->toContain('resolvePendingQuote');
});

it('formatea el numero de cotizacion de coordinacion y genera url de aprobacion', function (): void {
    expect(\App\Support\Operations\CoordinationServiceQuoteManager::formatCoordinationQuoteNumber(20))
        ->toBe('COT-000020');
});
