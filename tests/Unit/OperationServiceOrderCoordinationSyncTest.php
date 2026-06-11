<?php

declare(strict_types=1);

use App\Support\Operations\OperationServiceOrderCoordinationSync;

it('define sincronizacion de items de coordinacion al finalizar orden', function (): void {
    $syncPath = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderCoordinationSync.php');
    $viewPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ViewOperationServiceOrder.php');
    $tablePath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($syncPath)
        ->toContain('final class OperationServiceOrderCoordinationSync')
        ->toContain('function finalizeClinicalItemsForOrder')
        ->toContain('function cancelClinicalItemsForOrder')
        ->toContain('updateMatchedRecords')
        ->toContain("'FINALIZADO'")
        ->toContain("'CANCELADA'")
        ->toContain('refreshCoordinationStatus')
        ->toContain('LABORATORIOS')
        ->toContain('TelemedicinePatientLab');

    expect($viewPath)->toContain('OperationServiceOrderCoordinationSync::finalizeOrder');
    expect($tablePath)->toContain('OperationServiceOrderCoordinationSync::finalizeOrder');
});

it('detecta cuando todos los items de coordinacion estan finalizados', function (): void {
    $items = collect([
        ['status' => 'FINALIZADO'],
        ['status' => 'FINALIZADO'],
    ]);

    expect(OperationServiceOrderCoordinationSync::allItemsAreFinalized($items))->toBeTrue();

    $mixed = collect([
        ['status' => 'FINALIZADO'],
        ['status' => 'EN GESTION'],
    ]);

    expect(OperationServiceOrderCoordinationSync::allItemsAreFinalized($mixed))->toBeFalse();
});
