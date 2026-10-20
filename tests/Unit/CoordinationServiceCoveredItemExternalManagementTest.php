<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\CoordinationServiceItemExternalManagement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');

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

function labForExternalTest(OperationCoordinationService $coordination, string $type = 'CUBIERTO', string $name = 'PERFIL LIPIDICO PRUEBA'): TelemedicinePatientLab
{
    return TelemedicinePatientLab::query()->create([
        'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $coordination->id,
        'laboratory' => $name,
        'type' => $type,
        'status' => 'PENDIENTE',
    ]);
}

function actAsOperationsAnalyst(bool $superAdmin): void
{
    Auth::setUser(User::factory()->make([
        'id' => 999999999,
        'name' => 'ANALISTA PRUEBA',
        'departament' => $superAdmin ? ['SUPERADMIN', 'OPERACIONES'] : ['OPERACIONES'],
    ]));
}

it('registra el permiso asignable de gestión externa de cubiertos en Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::EXTERNAL_MANAGE_COVERED_ITEMS))
        ->toBe(['OPERACIONES'])
        ->and(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::EXTERNAL_MANAGE_COVERED_ITEMS);
});

it('habilita cubiertos y sin dato solo con permiso; no cubiertos siempre', function (): void {
    expect(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(false, false))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(true, false))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(true, true))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(null, false))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(null, true))->toBeTrue();
});

it('no deja cerrar un cubierto sin el permiso, aunque se llame directo', function (): void {
    actAsOperationsAnalyst(superAdmin: false);
    $lab = labForExternalTest($this->coordination);
    Storage::disk('public')->put('operation-coordination-services/'.$this->coordination->id.'/documents/soporte.pdf', 'pdf');

    CoordinationServiceItemExternalManagement::markAsExternallyManaged(
        $this->coordination, 'lab', $lab->id, 'Laboratorio: PERFIL LIPIDICO PRUEBA',
        'El paciente se lo hizo en otro laboratorio.',
        'operation-coordination-services/'.$this->coordination->id.'/documents/soporte.pdf',
    );

    expect($lab->fresh()->status)->toBe('PENDIENTE')
        ->and(Storage::disk('public')->exists('operation-coordination-services/'.$this->coordination->id.'/documents/soporte.pdf'))->toBeFalse();
});

it('cierra un cubierto con permiso, guarda el soporte y deja la cobertura en la bitácora', function (): void {
    actAsOperationsAnalyst(superAdmin: true);
    $lab = labForExternalTest($this->coordination);
    $path = 'operation-coordination-services/'.$this->coordination->id.'/documents/soporte.pdf';
    Storage::disk('public')->put($path, 'pdf');
    $documentsBefore = count(is_array($this->coordination->uploaded_documents) ? $this->coordination->uploaded_documents : []);

    CoordinationServiceItemExternalManagement::markAsExternallyManaged(
        $this->coordination, 'lab', $lab->id, 'Laboratorio: PERFIL LIPIDICO PRUEBA',
        'El paciente se lo hizo en otro laboratorio.', $path, 'informe-lab.pdf',
    );

    $coordination = $this->coordination->fresh();
    $documents = $coordination->uploaded_documents;
    $lastDocument = end($documents);

    expect($lab->fresh()->status)->toBe('FINALIZADO')
        ->and(count($documents))->toBe($documentsBefore + 1)
        ->and($lastDocument['file_path'])->toBe($path)
        ->and($lastDocument['document_name'])->toBe('informe-lab')
        ->and($lastDocument['service_item_keys'])->toBe(['lab:'.$lab->id])
        ->and(ObservationCase::query()->where('telemedicine_case_id', $coordination->telemedicine_case_id)->latest('id')->value('description'))
        ->toContain('Cobertura: CUBIERTO')
        ->toContain('Soporte: adjunto');
});

it('un no cubierto se cierra sin permiso y sin guardar soporte', function (): void {
    actAsOperationsAnalyst(superAdmin: false);
    $lab = labForExternalTest($this->coordination, 'NO CUBIERTO');

    CoordinationServiceItemExternalManagement::markAsExternallyManaged(
        $this->coordination, 'lab', $lab->id, 'Laboratorio: PERFIL LIPIDICO PRUEBA', 'Lo resolvió por su cuenta en otro centro.',
    );

    expect($lab->fresh()->status)->toBe('FINALIZADO');
});

it('bloquea el ítem que está en una orden activa', function (): void {
    actAsOperationsAnalyst(superAdmin: true);
    $lab = labForExternalTest($this->coordination);
    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $this->coordination->id,
        'order_number' => 'ORD-TEST-EXT',
        'service_type' => 'LABORATORIOS',
        'status' => 'EN GESTION',
        'created_by' => 'TEST',
    ]);
    OperationServiceOrderItem::query()->create([
        'operation_service_order_id' => $order->id,
        'item_name' => 'perfil lipidico prueba',
        'category' => 'LABORATORIOS',
    ]);

    expect(CoordinationServiceItemExternalManagement::blockingReason($this->coordination, 'lab', $lab))
        ->toContain('orden de servicio ORD-TEST-EXT');

    CoordinationServiceItemExternalManagement::markAsExternallyManaged(
        $this->coordination, 'lab', $lab->id, 'Laboratorio: PERFIL LIPIDICO PRUEBA', 'El paciente se lo hizo en otro lado.',
    );

    expect($lab->fresh()->status)->toBe('PENDIENTE');

    $order->update(['status' => 'CANCELADA']);

    expect(CoordinationServiceItemExternalManagement::blockingReason($this->coordination, 'lab', $lab))->toBeNull();
});

it('bloquea el medicamento ligado a una orden por vínculo y el ítem en cotización pendiente', function (): void {
    $medication = TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $this->coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $this->coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $this->coordination->id,
        'medicine' => 'AMOXICILINA PRUEBA',
        'indications' => 'Cada 8 horas',
        'status' => 'EN GESTION',
        'is_covered' => true,
    ]);
    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $this->coordination->id,
        'order_number' => 'ORD-TEST-MED-EXT',
        'service_type' => 'MEDICAMENTOS',
        'status' => 'EN GESTION',
        'created_by' => 'TEST',
    ]);
    OperationServiceOrderItem::query()->create([
        'operation_service_order_id' => $order->id,
        'telemedicine_patient_medication_id' => $medication->id,
        'item_name' => 'Nombre editado',
        'category' => 'MEDICAMENTOS',
    ]);

    expect(CoordinationServiceItemExternalManagement::blockingReason($this->coordination, 'medication', $medication))
        ->toContain('ORD-TEST-MED-EXT');

    $lab = labForExternalTest($this->coordination, 'CUBIERTO', 'GLICEMIA PRUEBA');
    $quote = OperationQuoteGenerator::query()->create([
        'operation_coordination_service_id' => $this->coordination->id,
        'type_service' => 'LABORATORIOS',
        'status' => OperationQuoteGenerator::STATUS_PENDING,
        'items' => [['key' => 'lab:'.$lab->id, 'label' => 'GLICEMIA PRUEBA']],
        'created_by' => 'TEST',
    ]);

    expect(CoordinationServiceItemExternalManagement::blockingReason($this->coordination, 'lab', $lab))
        ->toContain('cotización #'.$quote->id);

    $quote->update(['status' => OperationQuoteGenerator::STATUS_REJECTED]);

    expect(CoordinationServiceItemExternalManagement::blockingReason($this->coordination, 'lab', $lab))->toBeNull();
});

it('lee la cobertura del propio ítem', function (): void {
    expect(CoordinationServiceItemExternalManagement::itemCoverage(new TelemedicinePatientLab(['type' => 'CUBIERTO'])))->toBeTrue()
        ->and(CoordinationServiceItemExternalManagement::itemCoverage(new TelemedicinePatientLab(['type' => 'no cubierto'])))->toBeFalse()
        ->and(CoordinationServiceItemExternalManagement::itemCoverage(new TelemedicinePatientLab(['type' => ''])))->toBeNull()
        ->and(CoordinationServiceItemExternalManagement::itemCoverage(new TelemedicinePatientMedications(['is_covered' => true])))->toBeTrue();
});

it('el soporte opcional solo se ofrece en ítems cubiertos y el detalle oculta la acción con orden activa', function (): void {
    $covered = CoordinationServiceItemExternalManagement::makeExternalManagementAction([
        'id' => 7, 'item_type' => 'lab', 'title' => 'Laboratorio: X', 'status' => 'PENDIENTE', 'coverage' => true, 'can_external_management' => true,
    ]);

    expect($covered)->not->toBeNull();

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemExternalManagement.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($source)
        ->toContain("FileUpload::make('external_management_support')")
        ->toContain('->visible(! $isFreeOfCharge)')
        ->toContain('Soporte (opcional)');

    expect($infolist)->toContain("&& ! \$hasServiceOrder\n                && CoordinationServiceItemExternalManagement::coverageAllowsExternalManagement(\$coverage)");
});
