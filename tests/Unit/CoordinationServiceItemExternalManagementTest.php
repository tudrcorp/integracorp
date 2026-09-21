<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceItemExternalManagement;

it('solo permite gestión externa en ítems pendientes o en gestión', function (): void {
    expect(CoordinationServiceItemExternalManagement::statusIsManageable('PENDIENTE'))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::statusIsManageable('EN GESTION'))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::statusIsManageable('FINALIZADO'))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::statusIsManageable('CANCELADA'))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::statusIsManageable('CADUCADA'))->toBeFalse();
});

it('normaliza el estatus antes de evaluarlo', function (): void {
    expect(CoordinationServiceItemExternalManagement::statusIsManageable('  pendiente  '))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::statusIsManageable('en gestion'))->toBeTrue();
});

it('solo habilita la gestión externa en ítems explícitamente no cubiertos', function (): void {
    expect(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(false))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(true))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(null))->toBeFalse();
});

it('resuelve el modelo clínico según el tipo de ítem', function (): void {
    expect(CoordinationServiceItemExternalManagement::clinicalItemModelClass('medication'))
        ->toBe(\App\Models\TelemedicinePatientMedications::class)
        ->and(CoordinationServiceItemExternalManagement::clinicalItemModelClass('lab'))
        ->toBe(\App\Models\TelemedicinePatientLab::class)
        ->and(CoordinationServiceItemExternalManagement::clinicalItemModelClass('study'))
        ->toBe(\App\Models\TelemedicinePatientStudy::class)
        ->and(CoordinationServiceItemExternalManagement::clinicalItemModelClass('specialty'))
        ->toBe(\App\Models\TelemedicinePatientSpecialty::class)
        ->and(CoordinationServiceItemExternalManagement::clinicalItemModelClass('otro'))
        ->toBeNull();
});

it('construye la descripción de bitácora con prefijo, ítem y nota', function (): void {
    $description = CoordinationServiceItemExternalManagement::buildBitacoraDescription(
        'Estudio: ANGIOTOMOGRAFIA CEREBRAL (TAC)',
        'El paciente se realizó el estudio por su cuenta.',
    );

    expect($description)
        ->toContain(CoordinationServiceItemExternalManagement::OBSERVATION_PREFIX)
        ->toContain('Ítem: Estudio: ANGIOTOMOGRAFIA CEREBRAL (TAC)')
        ->toContain('Nota: El paciente se realizó el estudio por su cuenta.');
});

it('crea la acción de gestión externa solo cuando el ítem la habilita', function (): void {
    $action = CoordinationServiceItemExternalManagement::makeExternalManagementAction([
        'id' => 15,
        'item_type' => 'study',
        'title' => 'Estudio: ANGIORESONANCIA',
        'status' => 'PENDIENTE',
        'coverage' => false,
        'can_external_management' => true,
    ]);

    expect($action)->not->toBeNull()
        ->and($action->getLabel())->toBe('Gestión Externa');
});

it('no crea la acción cuando el ítem no la habilita', function (): void {
    $action = CoordinationServiceItemExternalManagement::makeExternalManagementAction([
        'id' => 15,
        'item_type' => 'study',
        'title' => 'Estudio: ANGIORESONANCIA',
        'status' => 'FINALIZADO',
        'coverage' => false,
        'can_external_management' => false,
    ]);

    expect($action)->toBeNull();
});

it('no crea la acción cuando el tipo de ítem es desconocido', function (): void {
    $action = CoordinationServiceItemExternalManagement::makeExternalManagementAction([
        'id' => 15,
        'item_type' => 'otro',
        'title' => 'Ítem raro',
        'status' => 'PENDIENTE',
        'coverage' => false,
        'can_external_management' => true,
    ]);

    expect($action)->toBeNull();
});

it('no crea la acción cuando el identificador del ítem es inválido', function (): void {
    $action = CoordinationServiceItemExternalManagement::makeExternalManagementAction([
        'id' => 0,
        'item_type' => 'study',
        'title' => 'Estudio sin id',
        'status' => 'PENDIENTE',
        'coverage' => false,
        'can_external_management' => true,
    ]);

    expect($action)->toBeNull();
});

it('no reutiliza el icono ni el color de las otras acciones de la fila', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemExternalManagement.php');

    expect($support)
        ->toContain('Heroicon::OutlinedDocumentCheck')
        ->toContain("->color('success')")
        ->not->toContain('OutlinedPencilSquare')
        ->not->toContain('OutlinedArrowTopRightOnSquare')
        ->not->toContain("->color('warning')");
});

it('deja el ítem en FINALIZADO y exige una nota mínima', function (): void {
    expect(CoordinationServiceItemExternalManagement::FINAL_STATUS)->toBe('FINALIZADO')
        ->and(CoordinationServiceItemExternalManagement::MINIMUM_OBSERVATION_LENGTH)->toBe(10);
});
