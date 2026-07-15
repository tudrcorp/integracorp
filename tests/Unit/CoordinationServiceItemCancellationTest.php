<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceItemCancellation;

it('solo permite cancelar ítems pendientes o en gestión', function (): void {
    expect(CoordinationServiceItemCancellation::statusIsCancellable('PENDIENTE'))->toBeTrue()
        ->and(CoordinationServiceItemCancellation::statusIsCancellable('EN GESTION'))->toBeTrue()
        ->and(CoordinationServiceItemCancellation::statusIsCancellable('FINALIZADO'))->toBeFalse()
        ->and(CoordinationServiceItemCancellation::statusIsCancellable('CANCELADA'))->toBeFalse()
        ->and(CoordinationServiceItemCancellation::statusIsCancellable('CADUCADA'))->toBeFalse();
});

it('resuelve el modelo clínico según el tipo de ítem', function (): void {
    expect(CoordinationServiceItemCancellation::clinicalItemModelClass('medication'))
        ->toBe(\App\Models\TelemedicinePatientMedications::class)
        ->and(CoordinationServiceItemCancellation::clinicalItemModelClass('lab'))
        ->toBe(\App\Models\TelemedicinePatientLab::class)
        ->and(CoordinationServiceItemCancellation::clinicalItemModelClass('study'))
        ->toBe(\App\Models\TelemedicinePatientStudy::class)
        ->and(CoordinationServiceItemCancellation::clinicalItemModelClass('specialty'))
        ->toBe(\App\Models\TelemedicinePatientSpecialty::class)
        ->and(CoordinationServiceItemCancellation::clinicalItemModelClass('otro'))
        ->toBeNull();
});

it('construye la descripción de bitácora con prefijo, ítem y motivo', function (): void {
    $description = CoordinationServiceItemCancellation::buildBitacoraDescription(
        'Laboratorio: HEMATOLOGIA COMPLETA (HC)',
        'El paciente desistió del examen.',
    );

    expect($description)
        ->toContain(CoordinationServiceItemCancellation::OBSERVATION_PREFIX)
        ->toContain('Ítem: Laboratorio: HEMATOLOGIA COMPLETA (HC)')
        ->toContain('Motivo: El paciente desistió del examen.');
});

it('crea la acción de cancelación solo cuando el ítem es cancelable', function (): void {
    $action = CoordinationServiceItemCancellation::makeCancelAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: COPROANALISIS',
        'status' => 'PENDIENTE',
        'can_cancel' => true,
    ]);

    expect($action)->not->toBeNull()
        ->and($action?->getName())->toBe('cancelAssociatedItemManagement_lab_15')
        ->and($action?->getLabel())->toBe('Cancelar gestión')
        ->and($action?->getTooltip())->toBe('Cancelar gestión');

    expect(CoordinationServiceItemCancellation::makeCancelAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: COPROANALISIS',
        'status' => 'FINALIZADO',
        'can_cancel' => false,
    ]))->toBeNull();
});

it('expone cancelación de ítems en el infolist de coordinación', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemCancellation.php');

    expect($infolist)
        ->toContain('CoordinationServiceItemCancellation')
        ->toContain('cancelAssociatedItemSuffixActions')
        ->toContain('medicationsItemsState')
        ->toContain('laboratoriesItemsState')
        ->toContain('studiesItemsState')
        ->toContain('specialtiesItemsState')
        ->toContain("'can_cancel'")
        ->toContain('fi-coordination-associated-item-card')
        ->toContain('associatedItemCardEntry')
        ->and($support)
        ->toContain('ObservationCase')
        ->toContain('cancellation_observation')
        ->toContain('->iconButton()')
        ->toContain("->tooltip('Cancelar gestión')");
});
