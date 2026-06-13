<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderViewActions;

it('expone botón de cancelar en el infolist de la orden de servicio', function (): void {
    $infolistPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php');
    $pagePath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ViewOperationServiceOrder.php');
    $actionsPath = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderViewActions.php');

    expect($infolistPath)
        ->toContain('OperationServiceOrderViewActions::makeCancelAction()')
        ->toContain('->footerActions([')
        ->toContain('->footerActionsAlignment(Alignment::End)');

    expect($pagePath)
        ->toContain('cancelServiceOrderAction')
        ->toContain('OperationServiceOrderViewActions::makeCancelAction()');

    expect($actionsPath)
        ->toContain("'status' => 'CANCELADA'")
        ->toContain('OperationServiceOrderCoordinationSync::cancelClinicalItemsForOrder');

    expect($actionsPath)
        ->toContain("Action::make('cancelServiceOrder')")
        ->toContain('Cancelar orden de servicio')
        ->toContain('OperationServiceOrderValidity::closedStatuses()')
        ->toContain('function canCancel')
        ->toContain('function cancelOrder')
        ->toContain('->action(function (OperationServiceOrder $record, mixed $livewire): void');
});

it('impide cancelar órdenes finalizadas o ya canceladas', function (): void {
    $finalized = new OperationServiceOrder(['status' => 'FINALIZADO']);
    $cancelled = new OperationServiceOrder(['status' => 'CANCELADA']);
    $active = new OperationServiceOrder(['status' => 'EN GESTION']);

    expect(OperationServiceOrderViewActions::canCancel($finalized))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canCancel($cancelled))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canCancel($active))->toBeTrue();
});
