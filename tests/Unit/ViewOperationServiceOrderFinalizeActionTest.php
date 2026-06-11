<?php

declare(strict_types=1);

it('expone acción de header para finalizar la orden de servicio', function (): void {
    $pagePath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ViewOperationServiceOrder.php');

    expect($pagePath)
        ->toContain("Action::make('finalize_service_order')")
        ->toContain('Finalizar orden')
        ->toContain('ActionGroup::make')
        ->toContain('PDFs y envío')
        ->toContain("->color('danger')")
        ->toContain('aviso-btn-ios-danger')
        ->toContain('configureOrderDocumentsModalAction')
        ->toContain("->modalSubmitActionLabel('Guardar')")
        ->toContain("->label('Guardar y Finalizar orden')")
        ->toContain('save_and_finalize_order_documents')
        ->toContain("->color('success')")
        ->toContain("->modalCancelActionLabel('Cancelar')")
        ->toContain('makeModalSubmitAction')
        ->toContain('OperationServiceOrderCoordinationSync::finalizeOrder')
        ->toContain('OperationServiceOrderViewActions::canFinalize')
        ->toContain('renderVigenciaHeaderPill')
        ->toContain('shouldHighlightVigencia');
});
