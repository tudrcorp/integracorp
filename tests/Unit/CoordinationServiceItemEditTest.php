<?php

declare(strict_types=1);

use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Operations\CoordinationServiceItemEdit;
use App\Support\Operations\CoordinationServiceItemsManager;

it('solo permite editar ítems en estatus pendiente', function (): void {
    expect(CoordinationServiceItemEdit::statusIsEditable('PENDIENTE'))->toBeTrue()
        ->and(CoordinationServiceItemEdit::statusIsEditable('  pendiente  '))->toBeTrue()
        ->and(CoordinationServiceItemEdit::statusIsEditable('EN GESTION'))->toBeFalse()
        ->and(CoordinationServiceItemEdit::statusIsEditable('FINALIZADO'))->toBeFalse()
        ->and(CoordinationServiceItemEdit::statusIsEditable('CANCELADA'))->toBeFalse()
        ->and(CoordinationServiceItemEdit::statusIsEditable('CADUCADA'))->toBeFalse();
});

it('bloquea la edición cuando el ítem ya tiene orden de servicio emitida', function (): void {
    $orderLinks = [
        CoordinationServiceItemsManager::clinicalItemServiceOrderKey('Laboratorio', 'HEMATOLOGIA COMPLETA') => [
            'id' => 9,
            'order_number' => 'OS-9',
            'status' => 'EN GESTION',
            'url' => '#',
        ],
    ];

    expect(CoordinationServiceItemEdit::itemHasServiceOrder($orderLinks, 'Laboratorio', 'hematologia completa'))->toBeTrue()
        ->and(CoordinationServiceItemEdit::itemHasServiceOrder($orderLinks, 'Laboratorio', 'COPROANALISIS'))->toBeFalse()
        ->and(CoordinationServiceItemEdit::itemHasServiceOrder($orderLinks, 'Estudio', 'HEMATOLOGIA COMPLETA'))->toBeFalse();

    expect(CoordinationServiceItemEdit::itemIsEditable('PENDIENTE', false))->toBeTrue()
        ->and(CoordinationServiceItemEdit::itemIsEditable('PENDIENTE', true))->toBeFalse()
        ->and(CoordinationServiceItemEdit::itemIsEditable('EN GESTION', false))->toBeFalse();
});

it('resuelve modelo, columna, categoría y catálogo según el tipo de ítem', function (): void {
    expect(CoordinationServiceItemEdit::clinicalItemModelClass('medication'))->toBe(TelemedicinePatientMedications::class)
        ->and(CoordinationServiceItemEdit::clinicalItemModelClass('lab'))->toBe(TelemedicinePatientLab::class)
        ->and(CoordinationServiceItemEdit::clinicalItemModelClass('study'))->toBe(TelemedicinePatientStudy::class)
        ->and(CoordinationServiceItemEdit::clinicalItemModelClass('specialty'))->toBe(TelemedicinePatientSpecialty::class)
        ->and(CoordinationServiceItemEdit::clinicalItemModelClass('otro'))->toBeNull();

    expect(CoordinationServiceItemEdit::nameColumn('medication'))->toBe('medicine')
        ->and(CoordinationServiceItemEdit::nameColumn('lab'))->toBe('laboratory')
        ->and(CoordinationServiceItemEdit::nameColumn('study'))->toBe('study')
        ->and(CoordinationServiceItemEdit::nameColumn('specialty'))->toBe('specialty');

    expect(CoordinationServiceItemEdit::categoryForType('medication'))->toBe('Medicamento')
        ->and(CoordinationServiceItemEdit::categoryForType('lab'))->toBe('Laboratorio')
        ->and(CoordinationServiceItemEdit::categoryForType('study'))->toBe('Estudio')
        ->and(CoordinationServiceItemEdit::categoryForType('specialty'))->toBe('Especialista');

    expect(CoordinationServiceItemEdit::catalogModelClass('lab'))->toBe(TelemedicineListLaboratory::class)
        ->and(CoordinationServiceItemEdit::catalogModelClass('study'))->toBe(TelemedicineListStudy::class)
        ->and(CoordinationServiceItemEdit::catalogModelClass('specialty'))->toBe(TelemedicineListSpecialist::class)
        ->and(CoordinationServiceItemEdit::catalogModelClass('medication'))->toBeNull();

    expect(CoordinationServiceItemEdit::usesCatalog('lab'))->toBeTrue()
        ->and(CoordinationServiceItemEdit::usesCatalog('medication'))->toBeFalse();
});

it('etiqueta las opciones de catálogo con su cobertura', function (): void {
    expect(CoordinationServiceItemEdit::catalogOptionLabel('HEMATOLOGIA COMPLETA', 'CUBIERTO'))
        ->toBe('HEMATOLOGIA COMPLETA (CUBIERTO)')
        ->and(CoordinationServiceItemEdit::catalogOptionLabel('  RESONANCIA  ', 'no cubierto'))
        ->toBe('RESONANCIA (NO CUBIERTO)')
        ->and(CoordinationServiceItemEdit::catalogOptionLabel('SIN TIPO', ''))
        ->toBe('SIN TIPO');
});

it('construye la descripción de bitácora con ítem, cambios y motivo', function (): void {
    $description = CoordinationServiceItemEdit::buildBitacoraDescription(
        'Laboratorio: HEMATOLOGIA COMPLETA',
        [
            'laboratory' => ['label' => 'Examen de laboratorio', 'from' => 'HEMATOLOGIA COMPLETA', 'to' => 'PERFIL 20'],
            'type' => ['label' => 'Cobertura', 'from' => '', 'to' => 'NO CUBIERTO'],
        ],
        'El médico corrigió la indicación en la consulta.',
    );

    expect($description)
        ->toContain(CoordinationServiceItemEdit::OBSERVATION_PREFIX)
        ->toContain('Ítem: Laboratorio: HEMATOLOGIA COMPLETA')
        ->toContain('- Examen de laboratorio: HEMATOLOGIA COMPLETA → PERFIL 20')
        ->toContain('- Cobertura: — → NO CUBIERTO')
        ->toContain('Motivo: El médico corrigió la indicación en la consulta.');
});

it('detecta cambios reales de indicaciones en medicamentos', function (): void {
    $medication = new TelemedicinePatientMedications(['indications' => '1 tableta cada 8 horas']);

    expect(CoordinationServiceItemEdit::resolveChanges($medication, 'medication', 'medicine', [
        'indications' => '1 tableta cada 12 horas',
    ]))->toHaveKey('indications');

    expect(CoordinationServiceItemEdit::resolveChanges($medication, 'medication', 'medicine', [
        'indications' => '  1 tableta cada 8 horas  ',
    ]))->toBe([]);
});

it('crea la acción de edición solo cuando el ítem es editable', function (): void {
    $action = CoordinationServiceItemEdit::makeEditAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: COPROANALISIS',
        'status' => 'PENDIENTE',
        'can_edit' => true,
    ]);

    expect($action)->not->toBeNull()
        ->and($action?->getName())->toBe('editAssociatedItem_lab_15')
        ->and($action?->getLabel())->toBe('Editar ítem')
        ->and($action?->getTooltip())->toBe('Editar ítem');

    expect(CoordinationServiceItemEdit::makeEditAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: COPROANALISIS',
        'status' => 'EN GESTION',
        'can_edit' => false,
    ]))->toBeNull();

    expect(CoordinationServiceItemEdit::makeEditAction([
        'id' => 15,
        'item_type' => 'inexistente',
        'title' => 'Ítem raro',
        'status' => 'PENDIENTE',
        'can_edit' => true,
    ]))->toBeNull();
});

it('exige el motivo en todos los formularios y fija el medicamento como no editable', function (): void {
    foreach (['medication', 'lab', 'study', 'specialty'] as $itemType) {
        $names = array_map(
            static fn ($field): ?string => $field->getName(),
            CoordinationServiceItemEdit::formSchema($itemType),
        );

        expect($names)->toContain('edit_observation');
    }

    $medicationFields = collect(CoordinationServiceItemEdit::formSchema('medication'))
        ->keyBy(fn ($field): string => (string) $field->getName());

    expect($medicationFields->keys()->all())->toContain('item_name', 'indications')
        ->and($medicationFields->get('item_name')->isDisabled())->toBeTrue();

    $labFields = collect(CoordinationServiceItemEdit::formSchema('lab'))
        ->keyBy(fn ($field): string => (string) $field->getName());

    expect($labFields->keys()->all())->toContain('catalog_id')
        ->and($labFields->keys()->all())->not->toContain('type');

    expect(CoordinationServiceItemEdit::formSchema('inexistente'))->toBe([]);
});

it('expone la edición de ítems en el infolist y enlaza la tabla al tab de ítems asociados', function (): void {
    $root = dirname(__DIR__, 2);
    $infolist = file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');
    $manager = file_get_contents($root.'/app/Support/Operations/CoordinationServiceItemsManager.php');
    $support = file_get_contents($root.'/app/Support/Operations/CoordinationServiceItemEdit.php');

    expect($infolist)
        ->toContain('CoordinationServiceItemEdit')
        ->toContain("'can_edit'")
        ->toContain('hasServiceOrder')
        ->toContain('persistTabInQueryString')
        ->toContain('ASSOCIATED_ITEMS_TAB')
        ->and($manager)
        ->toContain('associatedItemsTabUrl')
        ->toContain('OperationCoordinationServiceInfolist::ASSOCIATED_ITEMS_TAB')
        ->and($support)
        ->toContain('ObservationCase')
        ->toContain('edit_observation')
        ->toContain('->iconButton()')
        ->toContain("->tooltip('Editar ítem')");
});
