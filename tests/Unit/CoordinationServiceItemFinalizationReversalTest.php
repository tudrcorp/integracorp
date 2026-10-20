<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\CoordinationServiceItemFinalizationReversal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

const REVERSAL_NOTE = 'Se finalizó por error; el paciente aún no recibe el servicio. Autorizado por la coordinadora.';

beforeEach(function (): void {
    DB::beginTransaction();

    $coordination = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_case_id')
        ->whereNotNull('telemedicine_patient_id')
        ->first();

    if (! $coordination instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con caso y paciente para colgar los datos de prueba.');
    }

    $this->coordination = $coordination;
});

afterEach(fn () => DB::rollBack());

function actAsReversalAnalyst(bool $superAdmin): void
{
    Auth::setUser(User::factory()->make([
        'id' => 999999998,
        'name' => 'ANALISTA PRUEBA',
        'departament' => $superAdmin ? ['SUPERADMIN', 'OPERACIONES'] : ['OPERACIONES'],
    ]));
}

function finalizedLabForReversal(OperationCoordinationService $coordination, string $type = 'NO CUBIERTO'): TelemedicinePatientLab
{
    return TelemedicinePatientLab::query()->create([
        'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $coordination->id,
        'laboratory' => 'UROANALISIS PRUEBA',
        'type' => $type,
        'status' => 'FINALIZADO',
    ]);
}

function orderWithItemForReversal(OperationCoordinationService $coordination, string $status, string $category, string $name, ?int $medicationId = null): OperationServiceOrder
{
    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $coordination->id,
        'order_number' => 'ORD-REV-'.uniqid(),
        'service_type' => $category,
        'status' => $status,
        'created_by' => 'TEST',
    ]);

    OperationServiceOrderItem::query()->create([
        'operation_service_order_id' => $order->id,
        'telemedicine_patient_medication_id' => $medicationId,
        'item_name' => $name,
        'category' => $category,
    ]);

    return $order;
}

it('registra el permiso asignable de reversión en Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::REVERSE_FINALIZED_ITEMS)
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::REVERSE_FINALIZED_ITEMS))->toBe(['OPERACIONES']);
});

it('reversa un ítem finalizado a PENDIENTE, reabre la coordinación y deja traza', function (): void {
    actAsReversalAnalyst(superAdmin: true);
    $lab = finalizedLabForReversal($this->coordination, 'CUBIERTO');
    $this->coordination->forceFill(['status' => 'FINALIZADO'])->saveQuietly();

    $reversed = CoordinationServiceItemFinalizationReversal::reverse(
        $this->coordination, 'lab', $lab->id, 'Laboratorio: UROANALISIS PRUEBA', REVERSAL_NOTE, true,
    );

    $coordination = $this->coordination->fresh();
    $bitacora = ObservationCase::query()->where('telemedicine_case_id', $coordination->telemedicine_case_id)->latest('id')->value('description');

    expect($reversed)->toBeTrue()
        ->and($lab->fresh()->status)->toBe('PENDIENTE')
        ->and($coordination->status)->toBe('EN GESTION')
        ->and($coordination->observations)->toContain(CoordinationServiceItemFinalizationReversal::OBSERVATION_PREFIX)
        ->and($bitacora)
        ->toContain('Estatus: FINALIZADO → PENDIENTE')
        ->toContain('Cobertura: CUBIERTO')
        ->toContain('Autorización: confirmada por el analista')
        ->toContain('Motivo: '.REVERSAL_NOTE)
        ->toContain('La coordinación se reabrió');
});

it('no reversa sin permiso, sin confirmar autorización o con nota corta', function (): void {
    $lab = finalizedLabForReversal($this->coordination);

    actAsReversalAnalyst(superAdmin: false);
    expect(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'lab', $lab->id, 'Laboratorio', REVERSAL_NOTE, true))->toBeFalse();

    actAsReversalAnalyst(superAdmin: true);
    expect(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'lab', $lab->id, 'Laboratorio', REVERSAL_NOTE, false))->toBeFalse()
        ->and(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'lab', $lab->id, 'Laboratorio', 'muy corta', true))->toBeFalse()
        ->and($lab->fresh()->status)->toBe('FINALIZADO');
});

it('solo reversa ítems en FINALIZADO', function (): void {
    actAsReversalAnalyst(superAdmin: true);
    $lab = finalizedLabForReversal($this->coordination);
    $lab->update(['status' => 'CANCELADA']);

    expect(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'lab', $lab->id, 'Laboratorio', REVERSAL_NOTE, true))->toBeFalse()
        ->and($lab->fresh()->status)->toBe('CANCELADA')
        ->and(CoordinationServiceItemFinalizationReversal::statusIsReversible(' finalizado '))->toBeTrue()
        ->and(CoordinationServiceItemFinalizationReversal::statusIsReversible('PENDIENTE'))->toBeFalse();
});

it('bloquea el ítem finalizado con una orden de servicio finalizada', function (): void {
    actAsReversalAnalyst(superAdmin: true);
    $study = TelemedicinePatientStudy::query()->create([
        'telemedicine_patient_id' => $this->coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $this->coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $this->coordination->id,
        'study' => 'RX TORAX PRUEBA',
        'type' => 'CUBIERTO',
        'status' => 'FINALIZADO',
    ]);
    orderWithItemForReversal($this->coordination, 'FINALIZADO', 'IMAGENOLOGIA', 'rx torax prueba');

    expect(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'study', $study->id, 'Estudio: RX TORAX PRUEBA', REVERSAL_NOTE, true))->toBeFalse()
        ->and($study->fresh()->status)->toBe('FINALIZADO');
});

it('vuelve a EN GESTION si el ítem sigue en una orden activa, y detecta medicamentos por vínculo', function (): void {
    actAsReversalAnalyst(superAdmin: true);
    $medication = TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $this->coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $this->coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $this->coordination->id,
        'medicine' => 'LORATADINA PRUEBA',
        'indications' => 'Diaria',
        'status' => 'FINALIZADO',
        'is_covered' => false,
    ]);
    orderWithItemForReversal($this->coordination, 'EN GESTION', 'MEDICAMENTOS', 'Otro nombre', $medication->id);

    expect(CoordinationServiceItemFinalizationReversal::reverse($this->coordination, 'medication', $medication->id, 'Medicamento: LORATADINA PRUEBA', REVERSAL_NOTE, true))->toBeTrue()
        ->and($medication->fresh()->status)->toBe('EN GESTION');
});

it('el botón solo se crea con permiso y advierte las consecuencias laborales', function (): void {
    expect(CoordinationServiceItemFinalizationReversal::makeReverseAction([
        'id' => 3, 'item_type' => 'lab', 'title' => 'Laboratorio: X', 'status' => 'FINALIZADO', 'coverage' => true, 'can_reverse' => false,
    ]))->toBeNull();

    $action = CoordinationServiceItemFinalizationReversal::makeReverseAction([
        'id' => 3, 'item_type' => 'lab', 'title' => 'Laboratorio: X', 'status' => 'FINALIZADO', 'coverage' => true, 'can_reverse' => true,
    ]);

    expect($action)->not->toBeNull()
        ->and($action->getLabel())->toBe('Reversar finalización')
        ->and(CoordinationServiceItemFinalizationReversal::LABOR_WARNING)->toContain('consecuencias a nivel laboral');

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemFinalizationReversal.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($source)
        ->toContain("Checkbox::make('reversal_authorized')")
        ->toContain('->accepted()')
        ->toContain('e(self::LABOR_WARNING)');

    expect($infolist)
        ->toContain('CoordinationServiceItemFinalizationReversal::makeReverseAction($row)')
        ->toContain('CoordinationServiceItemFinalizationReversal::userCanReverse()');
});
