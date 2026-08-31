<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderViewActions;

it('la anulación por error exige motivo restrictivo y queda en bitácora', function (): void {
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderViewActions.php');

    expect($actions)
        ->toContain('VOID_PREFIX')
        ->toContain('Anulación de orden de servicio por error.')
        ->toContain("Action::make('voidServiceOrderForRegeneration')")
        ->toContain('Anular por error')
        ->toContain("Textarea::make('void_reason')")
        ->toContain('->required()')
        ->toContain('->minLength(10)')
        ->toContain('ObservationCase::query()->create')
        ->toContain('telemedicine_case_id')
        ->toContain('releaseClinicalItemsForOrder')
        ->toContain('Los ítems cubiertos asociados volvieron a PENDIENTE')
        ->toContain('function voidOrderForRegeneration')
        ->toContain('function buildVoidBitacoraDescription')
        ->toContain('El motivo de la anulación debe tener al menos 10 caracteres.');
});

it('arma la bitácora con orden, analista y motivo', function (): void {
    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0082',
        'service_type' => 'IMAGENOLOGIA',
    ]);

    $text = OperationServiceOrderViewActions::buildVoidBitacoraDescription(
        $order,
        'Se cargó el proveedor equivocado en la orden.',
        'Ana Pérez'
    );

    expect($text)
        ->toContain(OperationServiceOrderViewActions::VOID_PREFIX)
        ->toContain('Orden: ORD-0082')
        ->toContain('Tipo: IMAGENOLOGIA')
        ->toContain('Analista: Ana Pérez')
        ->toContain('Motivo: Se cargó el proveedor equivocado en la orden.')
        ->toContain('PENDIENTE');
});

it('impide anular por error órdenes cerradas', function (): void {
    $finalized = new OperationServiceOrder(['status' => 'FINALIZADO']);
    $cancelled = new OperationServiceOrder(['status' => 'CANCELADA']);
    $expired = new OperationServiceOrder(['status' => 'CADUCADA']);
    $active = new OperationServiceOrder(['status' => 'EN GESTION']);

    expect(OperationServiceOrderViewActions::canVoidForRegeneration($finalized))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canVoidForRegeneration($cancelled))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canVoidForRegeneration($expired))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canVoidForRegeneration($active))->toBeTrue();
});
