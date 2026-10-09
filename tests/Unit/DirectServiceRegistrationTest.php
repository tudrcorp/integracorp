<?php

declare(strict_types=1);

use App\Enums\ClinicalServiceChannel;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\RegisterDirectService;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\AffiliateClinicalServiceUsage;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationDirectServiceRegistration;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\ClinicalUsageLedger;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\DirectServiceRegistration;
use App\Support\Operations\DirectServiceRegistrationException;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Escribe casos, coordinaciones, ítems y consumos de cupo: transacción revertida
 * obligatoria (CLAUDE.md §0.2).
 */
beforeEach(function (): void {
    DB::beginTransaction();
    AffiliateClinicalEntitlementResolver::flush();
    Filament::setCurrentPanel('operations');
});

afterEach(function (): void {
    DB::rollBack();
    AffiliateClinicalEntitlementResolver::flush();
});

function directRegistrationUser(array $departments = ['SUPERADMIN', 'OPERACIONES']): User
{
    $user = User::factory()->create([
        'email' => 'qa.registro.directo.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);

    test()->actingAs($user);

    return $user;
}

/**
 * Paciente con plan clínico completo y cupo de laboratorio disponible por casos distintos.
 */
function patientWithLabQuota(): ?TelemedicinePatient
{
    foreach (TelemedicinePatient::query()->orderByDesc('id')->limit(400)->get() as $patient) {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);
        $lab = $snapshot->hasPlan && $snapshot->isComplete ? $snapshot->forChannel(ClinicalServiceChannel::Laboratory) : null;

        if ($lab !== null && ! $lab->exhausted && $lab->quota !== null && $lab->quotaScope->value === 'DISTINCT_CASES') {
            return $patient;
        }
    }

    return null;
}

function patientWithoutPlan(): ?TelemedicinePatient
{
    foreach (TelemedicinePatient::query()->orderByDesc('id')->limit(400)->get() as $patient) {
        if (! AffiliateClinicalEntitlementResolver::forPatient($patient)->hasPlan) {
            return $patient;
        }
    }

    return null;
}

function firstCatalogName(string $catalog): string
{
    return (string) $catalog::query()->orderBy('name')->value('name');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function directPayload(TelemedicinePatient $patient, array $overrides = []): array
{
    return [
        'telemedicine_patient_id' => $patient->id,
        'case_mode' => DirectServiceRegistration::CASE_MODE_NEW,
        'service_line' => array_key_first(DirectServiceRegistration::serviceLineOptions()),
        'diagnosis' => 'CONTROL DE PRUEBA QA',
        'request_date' => now()->toDateString(),
        'service_date' => now()->toDateString(),
        ...$overrides,
    ];
}

/*
 * ---------------------------------------------------------------------------
 * Validación
 * ---------------------------------------------------------------------------
 */

it('rechaza datos incompletos o inválidos con un mensaje claro por campo', function (array $overrides, string $field): void {
    $patient = TelemedicinePatient::query()->firstOrFail();
    $lab = firstCatalogName(TelemedicineListLaboratory::class);

    $base = directPayload($patient, ['labs' => [['name' => $lab, 'coverage' => 'CUBIERTO']]]);

    try {
        DirectServiceRegistration::normalize([...$base, ...$overrides]);
        $this->fail('Debió rechazar los datos.');
    } catch (DirectServiceRegistrationException $exception) {
        expect($exception->errors)->toHaveKey($field)
            ->and($exception->getMessage())->not->toBe('');
    }
})->with([
    'sin paciente' => [['telemedicine_patient_id' => null], 'telemedicine_patient_id'],
    'caso existente sin elegir' => [['case_mode' => 'existing', 'telemedicine_case_id' => null], 'telemedicine_case_id'],
    'línea de servicio inventada' => [['service_line' => 'NO EXISTE'], 'service_line'],
    'diagnóstico vacío' => [['diagnosis' => ' '], 'diagnosis'],
    'servicio antes de la solicitud' => [['request_date' => now()->toDateString(), 'service_date' => now()->subDays(3)->toDateString()], 'service_date'],
    'cobertura inválida' => [['labs' => [['name' => 'X', 'coverage' => 'TAL VEZ']]], 'labs'],
    'ítem fuera del catálogo' => [['labs' => [['name' => 'LABORATORIO QUE NO EXISTE QA', 'coverage' => 'CUBIERTO']]], 'labs'],
    'servicio no permitido' => [['standalone' => [['name' => 'TELEMEDICINA', 'coverage' => 'CUBIERTO']]], 'standalone'],
    'medicamento sin médico' => [['medications' => [['name' => 'IBUPROFENO', 'coverage' => 'CUBIERTO', 'quantity' => 1, 'indications' => 'CADA 8 HORAS']]], 'prescribing_doctor_id'],
    'medicamento sin indicaciones' => [['prescribing_doctor_id' => 1, 'medications' => [['name' => 'IBUPROFENO', 'coverage' => 'CUBIERTO', 'quantity' => 1, 'indications' => '']]], 'medications'],
]);

it('rechaza un ítem repetido y un registro sin ítems', function (): void {
    $patient = TelemedicinePatient::query()->firstOrFail();
    $lab = firstCatalogName(TelemedicineListLaboratory::class);

    expect(fn () => DirectServiceRegistration::normalize(directPayload($patient, [
        'labs' => [['name' => $lab, 'coverage' => 'CUBIERTO'], ['name' => $lab, 'coverage' => 'NO CUBIERTO']],
    ])))->toThrow(DirectServiceRegistrationException::class, 'está repetido');

    expect(fn () => DirectServiceRegistration::normalize(directPayload($patient)))
        ->toThrow(DirectServiceRegistrationException::class, 'Agregue al menos un');
});

/*
 * ---------------------------------------------------------------------------
 * Registro completo
 * ---------------------------------------------------------------------------
 */

it('registra todas las categorías con caso nuevo, ítems enlazados, traza y bitácora', function (): void {
    $user = directRegistrationUser();
    $patient = patientWithoutPlan();
    $doctor = TelemedicineDoctor::query()->where('status', 'ACTIVO')->first();

    if ($patient === null || $doctor === null) {
        $this->markTestSkipped('Faltan un paciente sin plan clínico o un médico activo.');
    }

    $lab = firstCatalogName(TelemedicineListLaboratory::class);
    $study = firstCatalogName(TelemedicineListStudy::class);

    $registration = DirectServiceRegistration::register(directPayload($patient, [
        'prescribing_doctor_id' => $doctor->id,
        'labs' => [['name' => $lab, 'coverage' => 'CUBIERTO']],
        'studies' => [['name' => $study, 'coverage' => 'NO CUBIERTO']],
        'medications' => [['name' => 'Ibuprofeno 400 mg', 'coverage' => 'CUBIERTO', 'quantity' => 10, 'duration' => 5, 'indications' => 'UNA TABLETA CADA 8 HORAS']],
        'standalone' => [['name' => 'TRASLADO EN AMBULANCIA', 'coverage' => 'NO CUBIERTO']],
    ]), $user);

    $case = TelemedicineCase::query()->withoutGlobalScopes()->findOrFail($registration->telemedicine_case_id);
    $coordinations = OperationCoordinationService::query()->whereIn('id', $registration->coordination_ids)->get()->keyBy('specific_service');

    expect($registration->case_created)->toBeTrue()
        ->and($registration->registered_by_user_id)->toBe($user->id)
        ->and($case->status)->toBe(DirectServiceRegistration::CASE_STATUS)
        ->and($case->telemedicine_patient_id)->toBe($patient->id)
        ->and($coordinations->keys()->sort()->values()->all())->toBe(['IMAGENOLOGIA', 'LABORATORIOS', 'MEDICAMENTOS', 'TRASLADO EN AMBULANCIA'])
        ->and($coordinations->every(fn (OperationCoordinationService $c): bool => $c->direct_service_registration_id === $registration->id
            && $c->telemedicine_case_id === $case->id
            && $c->reference_number === $case->code
            && $c->status === 'PENDIENTE'))->toBeTrue()
        ->and($coordinations['LABORATORIOS']->telemedicinePatientLabs()->first()->type)->toBe('CUBIERTO')
        ->and($coordinations['IMAGENOLOGIA']->telemedicinePatientStudies()->first()->type)->toBe('NO CUBIERTO')
        ->and($coordinations['MEDICAMENTOS']->telemedicine_doctor_id)->toBe($doctor->id);

    $medication = $coordinations['MEDICAMENTOS']->telemedicinePatientMedications()->first();

    expect($medication->is_covered)->toBeTruthy()
        ->and($medication->telemedicine_doctor_id)->toBe($doctor->id)
        ->and((int) $medication->quantity)->toBe(10)
        ->and($medication->operation_inventory_id)->toBeNull();

    $ambulance = $coordinations['TRASLADO EN AMBULANCIA'];

    expect(RegisterTpaRetailServicesAction::isTpaRetailStandaloneCoordination($ambulance))->toBeTrue()
        ->and($ambulance->telemedicinePatientSpecialties()->first()->specialty)->toBe('TRASLADO EN AMBULANCIA')
        ->and(count($registration->items))->toBe(4)
        ->and($registration->clinical_usage_ids)->toBe([]);

    $bitacora = ObservationCase::query()->where('telemedicine_case_id', $case->id)->latest('id')->first();

    expect($bitacora->description)->toContain('REGISTRO DIRECTO DE SERVICIOS #'.$registration->id)
        ->toContain($lab.' (CUBIERTO)')
        ->toContain('Sin consumo de cupo clínico');
});

it('descuenta el cupo solo por lo cubierto, lo enlaza a su coordinación y no repite en el mismo caso', function (): void {
    $user = directRegistrationUser();
    $patient = patientWithLabQuota();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente con cupo de laboratorio por casos distintos.');
    }

    $lab = firstCatalogName(TelemedicineListLaboratory::class);

    $soloNoCubierto = DirectServiceRegistration::register(directPayload($patient, [
        'labs' => [['name' => $lab, 'coverage' => 'NO CUBIERTO']],
    ]), $user);

    expect($soloNoCubierto->clinical_usage_ids)->toBe([]);

    $primero = DirectServiceRegistration::register(directPayload($patient, [
        'labs' => [['name' => $lab, 'coverage' => 'CUBIERTO']],
    ]), $user);

    expect($primero->clinical_usage_ids)->toHaveCount(1);

    $uso = AffiliateClinicalServiceUsage::query()->findOrFail($primero->clinical_usage_ids[0]);

    expect($uso->channel)->toBe(ClinicalServiceChannel::Laboratory)
        ->and($uso->telemedicine_case_id)->toBe($primero->telemedicine_case_id)
        ->and((int) $uso->operation_coordination_service_id)->toBe($primero->coordination_ids[0])
        ->and($uso->status)->toBe(AffiliateClinicalServiceUsage::STATUS_CONSUMED);

    $otroLab = (string) TelemedicineListLaboratory::query()->orderByDesc('name')->value('name');

    $mismoCaso = DirectServiceRegistration::register(directPayload($patient, [
        'case_mode' => 'existing',
        'telemedicine_case_id' => $primero->telemedicine_case_id,
        'labs' => [['name' => $otroLab, 'coverage' => 'CUBIERTO']],
    ]), $user);

    expect($mismoCaso->case_created)->toBeFalse()
        ->and($mismoCaso->clinical_usage_ids)->toBe([$uso->id])
        ->and(AffiliateClinicalServiceUsage::query()->where('telemedicine_case_id', $primero->telemedicine_case_id)->where('status', 'CONSUMED')->count())->toBe(1);

    ClinicalUsageLedger::reverseForCase($primero->telemedicine_case_id);

    expect($uso->refresh()->status)->toBe(AffiliateClinicalServiceUsage::STATUS_REVERSED);
});

it('sin cupo bloquea lo cubierto y no deja nada a medias', function (): void {
    $user = directRegistrationUser();
    $patient = patientWithLabQuota();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente con cupo de laboratorio por casos distintos.');
    }

    $lab = firstCatalogName(TelemedicineListLaboratory::class);
    $guard = 0;

    /** Agota el cupo dentro de la transacción: un caso nuevo por registro. */
    while (! AffiliateClinicalEntitlementResolver::forPatient($patient->refresh())->forChannel(ClinicalServiceChannel::Laboratory)->exhausted && $guard++ < 20) {
        DirectServiceRegistration::register(directPayload($patient, ['labs' => [['name' => $lab, 'coverage' => 'CUBIERTO']]]), $user);
        AffiliateClinicalEntitlementResolver::flush();
    }

    $antes = [
        OperationDirectServiceRegistration::query()->count(),
        OperationCoordinationService::query()->count(),
        TelemedicineCase::query()->withoutGlobalScopes()->count(),
    ];

    expect(fn () => DirectServiceRegistration::register(directPayload($patient, [
        'labs' => [['name' => $lab, 'coverage' => 'CUBIERTO']],
    ]), $user))->toThrow(DirectServiceRegistrationException::class, 'sin cupo disponible');

    expect([
        OperationDirectServiceRegistration::query()->count(),
        OperationCoordinationService::query()->count(),
        TelemedicineCase::query()->withoutGlobalScopes()->count(),
    ])->toBe($antes);

    $comoNoCubierto = DirectServiceRegistration::register(directPayload($patient, [
        'labs' => [['name' => $lab, 'coverage' => 'NO CUBIERTO']],
    ]), $user);

    expect($comoNoCubierto->clinical_usage_ids)->toBe([]);
});

it('no suma servicios a un caso de otro paciente ni a un caso cerrado', function (): void {
    $user = directRegistrationUser();
    $patient = patientWithoutPlan();
    $otroCaso = TelemedicineCase::query()->where('telemedicine_patient_id', '!=', $patient?->id)->first();

    if ($patient === null || $otroCaso === null) {
        $this->markTestSkipped('Faltan datos de pacientes y casos.');
    }

    $lab = firstCatalogName(TelemedicineListLaboratory::class);

    expect(fn () => DirectServiceRegistration::register(directPayload($patient, [
        'case_mode' => 'existing',
        'telemedicine_case_id' => $otroCaso->id,
        'labs' => [['name' => $lab, 'coverage' => 'NO CUBIERTO']],
    ]), $user))->toThrow(DirectServiceRegistrationException::class, 'no pertenece a este paciente');

    $propio = DirectServiceRegistration::register(directPayload($patient, ['labs' => [['name' => $lab, 'coverage' => 'NO CUBIERTO']]]), $user);
    TelemedicineCase::query()->withoutGlobalScopes()->whereKey($propio->telemedicine_case_id)->update(['status' => 'ALTA MEDICA']);

    expect(fn () => DirectServiceRegistration::register(directPayload($patient, [
        'case_mode' => 'existing',
        'telemedicine_case_id' => $propio->telemedicine_case_id,
        'labs' => [['name' => $lab, 'coverage' => 'NO CUBIERTO']],
    ]), $user))->toThrow(DirectServiceRegistrationException::class, 'ya está cerrado');
});

/*
 * ---------------------------------------------------------------------------
 * Permiso y página
 * ---------------------------------------------------------------------------
 */

it('solo entra quien tiene el permiso; SUPERADMIN siempre', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::REGISTER_DIRECT_MEDICAL_SERVICE)
        ->and(BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::REGISTER_DIRECT_MEDICAL_SERVICE]['modules'])->toBe(['OPERACIONES']);

    $menu = App\Models\Permission::query()->where('slug', 'servicios-medicos')->where('module', 'OPERACIONES')->first();
    $accion = App\Models\Permission::query()->where('slug', BusinessFilamentActionPermissionRegistry::REGISTER_DIRECT_MEDICAL_SERVICE)->where('module', 'OPERACIONES')->first();

    if ($menu === null || $accion === null) {
        $this->markTestSkipped('Faltan los permisos; corra permissions:sync-navigation --panel=operations.');
    }

    /** Analista con acceso a Servicios médicos pero sin el permiso nuevo. */
    $analista = directRegistrationUser(['OPERACIONES']);
    $analista->permissions()->attach($menu->id);

    expect(RegisterDirectService::canAccess())->toBeFalse();
    Livewire::test(ListOperationCoordinationServices::class)->assertActionHidden('registerDirectService');
    Livewire::test(RegisterDirectService::class)->assertForbidden();

    /** El administrador le asigna el permiso. */
    $analista->permissions()->attach($accion->id);
    $analista->unsetRelation('permissions');
    $this->actingAs($analista->fresh());

    expect(RegisterDirectService::canAccess())->toBeTrue();
    Livewire::test(ListOperationCoordinationServices::class)->assertActionVisible('registerDirectService');

    directRegistrationUser(['SUPERADMIN']);
    expect(RegisterDirectService::canAccess())->toBeTrue();
});

it('la página registra el servicio de punta a punta y redirige al Cuadro de control', function (): void {
    directRegistrationUser();
    $patient = patientWithoutPlan();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente sin plan clínico.');
    }

    $lab = firstCatalogName(TelemedicineListLaboratory::class);
    $antes = OperationDirectServiceRegistration::query()->count();

    Livewire::test(RegisterDirectService::class)
        ->assertOk()
        ->assertSee('Operaciones · Servicios médicos')
        ->assertSee('Registre servicios sin pasar por la consulta médica.')
        ->assertDontSee('deja traza completa')
        ->set('data.telemedicine_patient_id', $patient->id)
        ->assertSee('Cupo clínico')
        ->set('data.service_line', array_key_first(DirectServiceRegistration::serviceLineOptions()))
        ->set('data.diagnosis', 'DOLOR ABDOMINAL QA')
        ->set('data.labs', ['fila-1' => ['name' => $lab, 'coverage' => 'NO CUBIERTO']])
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect();

    $registro = OperationDirectServiceRegistration::query()->latest('id')->first();

    expect(OperationDirectServiceRegistration::query()->count())->toBe($antes + 1)
        ->and($registro->telemedicine_patient_id)->toBe($patient->id)
        ->and($registro->items[0]['name'])->toBe($lab);
});

it('la página muestra el error de negocio sin guardar nada', function (): void {
    directRegistrationUser();
    $patient = patientWithoutPlan();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente sin plan clínico.');
    }

    $antes = OperationDirectServiceRegistration::query()->count();

    Livewire::test(RegisterDirectService::class)
        ->set('data.telemedicine_patient_id', $patient->id)
        ->set('data.service_line', array_key_first(DirectServiceRegistration::serviceLineOptions()))
        ->set('data.diagnosis', 'SIN ITEMS QA')
        ->call('register')
        ->assertNotified('No se registró el servicio');

    expect(OperationDirectServiceRegistration::query()->count())->toBe($antes);
});

it('la vista previa del cupo trae números, tipo y color por categoría', function (): void {
    directRegistrationUser();
    $patient = patientWithLabQuota();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente con cupo de laboratorio por casos distintos.');
    }

    $rows = collect(DirectServiceRegistration::quotaPreview($patient)['rows'])->keyBy('category');
    $lab = $rows['labs'];

    expect($rows->keys()->all())->toBe(['labs', 'studies', 'specialists', 'medications'])
        ->and($lab['kind'])->toBe('quota')
        ->and($lab['unit'])->toBe('casos')
        ->and($lab['remaining'])->toBe($lab['quota'] - $lab['used'])
        ->and($lab['tone'])->toBe(match (true) {
            $lab['remaining'] <= 0 => 'danger',
            $lab['remaining'] <= 1 => 'warning',
            default => 'success',
        });

    $sinPlan = patientWithoutPlan();

    if ($sinPlan !== null) {
        expect(collect(DirectServiceRegistration::quotaPreview($sinPlan)['rows'])->pluck('kind')->unique()->all())->toBe(['no_plan']);
    }
});

it('la página muestra la ficha del paciente y una tarjeta de cupo por categoría', function (): void {
    directRegistrationUser();
    $patient = patientWithLabQuota();

    if ($patient === null) {
        $this->markTestSkipped('No hay paciente con cupo de laboratorio por casos distintos.');
    }

    $lab = collect(DirectServiceRegistration::quotaPreview($patient)['rows'])->firstWhere('category', 'labs');

    Livewire::test(RegisterDirectService::class)
        ->set('data.telemedicine_patient_id', $patient->id)
        ->assertSee($patient->full_name)
        ->assertSee('Cupo clínico')
        ->assertSee('Solo lo cubierto descuenta cupo.')
        ->assertSee('de '.$lab['quota'].' casos disponibles')
        ->assertSeeHtml('role="progressbar"')
        ->assertSeeHtml('wire:key="quota-labs"')
        ->assertSeeHtml('wire:key="quota-medications"');
});
