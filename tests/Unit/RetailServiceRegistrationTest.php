<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\RegisterRetailServices;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\AffiliateClinicalServiceUsage;
use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Operations\AssignCoordinationServiceToSupplier;
use App\Support\Operations\CoordinationServiceAccess;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\RetailServiceProviderCatalog;
use App\Support\Operations\RetailServiceRegistration;
use App\Support\Operations\RetailServiceRegistrationException;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Escribe casos, coordinaciones e ítems: transacción revertida obligatoria (CLAUDE.md §0.2).
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

function retailAnalyst(): User
{
    $user = User::factory()->create([
        'email' => 'qa.retail.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
        'is_proveedor_amd' => false,
    ]);

    test()->actingAs($user);

    return $user;
}

function retailPatient(): TelemedicinePatient
{
    $patient = TelemedicinePatient::query()->whereNull('supplier_id')->orderByDesc('id')->first();

    if (! $patient instanceof TelemedicinePatient) {
        test()->markTestSkipped('La base no tiene pacientes de telemedicina sin proveedor.');
    }

    return $patient;
}

function retailSupplierKey(): string
{
    $id = Supplier::query()->where('gestion_integracorp', false)->orderBy('id')->value('id');

    if ($id === null) {
        test()->markTestSkipped('La base no tiene proveedores jurídicos.');
    }

    return RetailServiceProviderCatalog::key(RetailServiceProviderCatalog::TYPE_SUPPLIER, (int) $id);
}

/**
 * @return array{supplier_id: int, doctor_id: int}
 */
function retailManagedTeamSupplier(): array
{
    $supplierId = Supplier::query()
        ->where('gestion_integracorp', true)
        ->whereIn('id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('supplier_id'))
        ->orderBy('id')
        ->value('id');

    if ($supplierId === null) {
        test()->markTestSkipped('La base no tiene proveedores con gestión en Integracorp y médicos.');
    }

    return [
        'supplier_id' => (int) $supplierId,
        'doctor_id' => (int) TelemedicineDoctor::query()->where('supplier_id', $supplierId)->value('id'),
    ];
}

function retailTdgDoctorId(): int
{
    $id = TelemedicineDoctor::query()->where('managed_by', 'TDG')->value('id');

    if ($id === null) {
        test()->markTestSkipped('La base no tiene médicos TDG.');
    }

    return (int) $id;
}

function retailCaseVisibleTo(TelemedicineCase $case, int $doctorId): bool
{
    return TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases(TelemedicineCase::query()->whereKey($case->id), $doctorId)->exists();
}

it('interpreta solo claves de proveedor válidas', function (mixed $key, ?array $expected): void {
    expect(RetailServiceProviderCatalog::parseKey($key))->toBe($expected);
})->with([
    'jurídico' => ['juridico:15', ['type' => 'juridico', 'id' => 15]],
    'natural' => ['natural:3', ['type' => 'natural', 'id' => 3]],
    'aliado' => ['aliado:7', ['type' => 'aliado', 'id' => 7]],
    'equipo TDG' => ['equipo:TDG', ['type' => 'equipo', 'id' => null]],
    'equipo inventado' => ['equipo:15', null],
    'tipo desconocido' => ['clinica:1', null],
    'id no numérico' => ['juridico:1 OR 1=1', null],
    'id cero' => ['juridico:0', null],
    'sin separador' => ['15', null],
    'no es texto' => [15, null],
    'nulo' => [null, null],
]);

it('resuelve los tres tipos de proveedor contra la base y rechaza ids inexistentes', function (): void {
    retailAnalyst();

    $supplier = Supplier::query()->orderBy('id')->firstOrFail();
    $doctorNurse = DoctorNurse::query()->orderBy('id')->first();
    $ally = CorporateAlly::query()->orderBy('id')->first();

    expect(RetailServiceProviderCatalog::resolve('juridico:'.$supplier->id, 'LABORATORIOS'))
        ->toMatchArray(['type' => 'juridico', 'supplier_id' => $supplier->id, 'doctor_nurse_id' => null, 'corporate_ally_id' => null, 'team' => null])
        ->and(RetailServiceProviderCatalog::resolve('juridico:999999999', 'LABORATORIOS'))->toBeNull();

    if ($doctorNurse !== null) {
        expect(RetailServiceProviderCatalog::resolve('natural:'.$doctorNurse->id, 'ESPECIALISTA'))
            ->toMatchArray(['type' => 'natural', 'doctor_nurse_id' => $doctorNurse->id, 'supplier_id' => null]);
    }

    if ($ally !== null) {
        expect(RetailServiceProviderCatalog::resolve('aliado:'.$ally->id, 'MEDICAMENTOS'))
            ->toMatchArray(['type' => 'aliado', 'corporate_ally_id' => $ally->id, 'supplier_id' => null]);
    }
});

it('el equipo TDG solo se acepta en Telemedicina y AMD', function (): void {
    retailAnalyst();

    expect(RetailServiceProviderCatalog::resolve('equipo:TDG', 'TELEMEDICINA')['team'])->toMatchArray(['key' => 'TDG', 'supplier_id' => null, 'managed_by' => 'TDG'])
        ->and(RetailServiceProviderCatalog::resolve('equipo:TDG', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)')['team']['key'])->toBe('TDG')
        ->and(RetailServiceProviderCatalog::resolve('equipo:TDG', 'LABORATORIOS'))->toBeNull()
        ->and(RetailServiceProviderCatalog::resolve('equipo:TDG', 'TRASLADO EN AMBULANCIA'))->toBeNull();
});

it('un proveedor de gestión con médicos es equipo en Telemedicina y AMD, y proveedor común en lo demás', function (): void {
    retailAnalyst();
    $team = retailManagedTeamSupplier();

    expect(RetailServiceProviderCatalog::resolve('juridico:'.$team['supplier_id'], 'AMD (ASISTENCIA MEDICA DOMICILIARIA)')['team']['supplier_id'])->toBe($team['supplier_id'])
        ->and(RetailServiceProviderCatalog::resolve('juridico:'.$team['supplier_id'], 'LABORATORIOS')['team'])->toBeNull()
        ->and(RetailServiceProviderCatalog::resolve(retailSupplierKey(), 'TELEMEDICINA')['team'])->toBeNull();
});

it('el analista de un proveedor no puede mandar el caso al equipo TDG', function (): void {
    $team = retailManagedTeamSupplier();
    test()->actingAs((new User)->forceFill(['id' => 999_997, 'supplier_id' => $team['supplier_id'], 'is_proveedor_amd' => false, 'departament' => ['OPERACIONES']]));

    expect(RetailServiceProviderCatalog::resolve('equipo:TDG', 'TELEMEDICINA'))->toBeNull()
        ->and(RetailServiceProviderCatalog::teamOptions())->toHaveKey('juridico:'.$team['supplier_id'])
        ->and(RetailServiceProviderCatalog::teamOptions())->not->toHaveKey('equipo:TDG');
});

it('la búsqueda agrupa por tipo, exige 2 caracteres y no rompe con comodines', function (): void {
    retailAnalyst();
    $supplier = Supplier::query()->whereNotNull('name')->where('name', '!=', '')->orderBy('id')->firstOrFail();

    $results = RetailServiceProviderCatalog::search(mb_substr((string) $supplier->name, 0, 6), 'LABORATORIOS');

    expect(RetailServiceProviderCatalog::search('a', 'LABORATORIOS'))->toBe([])
        ->and($results)->toHaveKey('Proveedores jurídicos')
        ->and(array_keys($results['Proveedores jurídicos']))->each->toStartWith('juridico:')
        ->and(RetailServiceProviderCatalog::search('%_%', 'TELEMEDICINA'))->toBeArray();
});

it('sugiere equipos médicos en Telemedicina y proveedores con la capacidad del servicio', function (): void {
    retailAnalyst();

    expect(RetailServiceProviderCatalog::suggestions('TELEMEDICINA'))->toHaveKey('Equipos médicos · lo ve el médico en Telemedicina')
        ->and(RetailServiceProviderCatalog::suggestions('TELEMEDICINA')['Equipos médicos · lo ve el médico en Telemedicina'])->toHaveKey('equipo:TDG')
        ->and(RetailServiceProviderCatalog::suggestions('LABORATORIOS'))->not->toHaveKey('Equipos médicos · lo ve el médico en Telemedicina');

    $labSupplierId = Supplier::query()->where('laboratorio_centro', true)->value('id');

    if ($labSupplierId !== null) {
        $keys = array_keys(RetailServiceProviderCatalog::suggestions('LABORATORIOS')['Sugeridos para este servicio'] ?? []);
        expect($keys)->not->toBeEmpty()->each->toStartWith('juridico:');
    }
});

it('rechaza un registro vacío, sin proveedor o con proveedor inexistente', function (array $data, string $field): void {
    retailAnalyst();

    try {
        RetailServiceRegistration::normalize($data);
        $this->fail('Debió rechazar el registro.');
    } catch (RetailServiceRegistrationException $exception) {
        expect($exception->errors)->toHaveKey($field);
    }
})->with([
    'nada seleccionado' => [[], 'services'],
    'servicio no permitido' => [['services' => ['CIRUGIA']], 'services'],
    'servicio sin proveedor' => [['services' => ['TRASLADO EN AMBULANCIA']], 'service_providers.traslado_en_ambulancia'],
    'proveedor inexistente' => [['services' => ['APS'], 'service_providers' => ['aps' => 'juridico:999999999']], 'service_providers.aps'],
    'medicamento sin proveedor' => [['medications' => [['name' => 'Ibuprofeno', 'quantity' => 1, 'indications' => 'Cada 8 horas']]], 'medications_provider'],
]);

it('valida los medicamentos fila por fila', function (array $row, string $message): void {
    retailAnalyst();

    expect(fn () => RetailServiceRegistration::normalize(['medications' => [$row], 'medications_provider' => retailSupplierKey()]))
        ->toThrow(RetailServiceRegistrationException::class, $message);
})->with([
    'cantidad cero' => [['name' => 'Ibuprofeno', 'quantity' => 0, 'indications' => 'Cada 8 horas'], 'cantidad válida'],
    'cantidad texto' => [['name' => 'Ibuprofeno', 'quantity' => 'dos', 'indications' => 'Cada 8 horas'], 'cantidad válida'],
    'duración fuera de rango' => [['name' => 'Ibuprofeno', 'quantity' => 1, 'duration' => 5000, 'indications' => 'Cada 8 horas'], 'duración'],
    'sin indicaciones' => [['name' => 'Ibuprofeno', 'quantity' => 1, 'indications' => ' '], 'indicaciones'],
]);

it('rechaza medicamentos repetidos aunque cambie mayúsculas o espacios', function (): void {
    retailAnalyst();

    expect(fn () => RetailServiceRegistration::normalize([
        'medications' => [
            ['name' => 'Ibuprofeno 400', 'quantity' => 1, 'indications' => 'Cada 8 horas'],
            ['name' => ' IBUPROFENO   400 ', 'quantity' => 2, 'indications' => 'Cada 12 horas'],
        ],
        'medications_provider' => retailSupplierKey(),
    ]))->toThrow(RetailServiceRegistrationException::class, 'repetido');
});

it('exige el mismo equipo para Telemedicina y AMD, y el motivo cuando va a un equipo', function (): void {
    retailAnalyst();
    $team = retailManagedTeamSupplier();

    expect(fn () => RetailServiceRegistration::normalize([
        'services' => ['TELEMEDICINA', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)'],
        'service_providers' => ['telemedicina' => 'equipo:TDG', 'amd_asistencia_medica_domiciliaria' => 'juridico:'.$team['supplier_id']],
        'reason' => 'Fiebre alta desde ayer',
    ]))->toThrow(RetailServiceRegistrationException::class, 'mismo equipo médico');

    expect(fn () => RetailServiceRegistration::normalize([
        'services' => ['TELEMEDICINA'],
        'service_providers' => ['telemedicina' => 'equipo:TDG'],
        'reason' => '',
    ]))->toThrow(RetailServiceRegistrationException::class, 'motivo');
});

it('sin equipo médico: caso RETAIL solo para Operaciones, proveedor asignado y sin cupo clínico', function (): void {
    $actor = retailAnalyst();
    $patient = retailPatient();
    $supplierKey = retailSupplierKey();
    $lab = (string) TelemedicineListLaboratory::query()->orderBy('name')->value('name');
    $usageBefore = AffiliateClinicalServiceUsage::query()->count();

    $result = RetailServiceRegistration::register($patient, [
        'services' => ['TRASLADO EN AMBULANCIA'],
        'service_providers' => ['traslado_en_ambulancia' => $supplierKey],
        'labs' => [$lab],
        'labs_provider' => $supplierKey,
        'medications' => [['name' => 'Acetaminofén 500 mg', 'quantity' => 10, 'duration' => 5, 'indications' => '1 tableta cada 8 horas']],
        'medications_provider' => $supplierKey,
    ], $actor);

    $case = $result['case']->refresh();
    $coordinations = OperationCoordinationService::query()->whereIn('id', $result['coordination_ids'])->get()->keyBy('specific_service');
    $supplierId = (int) explode(':', $supplierKey)[1];

    expect($case->status)->toBe('RETAIL')
        ->and((bool) $case->assigned_to_medical_team)->toBeFalse()
        ->and($case->telemedicine_doctor_id)->toBeNull()
        ->and($result['team'])->toBeNull()
        ->and($coordinations->keys()->sort()->values()->all())->toBe(['LABORATORIOS', 'MEDICAMENTOS', 'TRASLADO EN AMBULANCIA'])
        ->and($coordinations->every(fn (OperationCoordinationService $c): bool => $c->servicie === 'TPA/RETAIL'
            && $c->status === 'PENDIENTE'
            && $c->assigned_provider_type === 'juridico'
            && (int) $c->assigned_supplier_id === $supplierId
            && filled($c->supplier_service)))->toBeTrue()
        ->and($coordinations['LABORATORIOS']->telemedicinePatientLabs()->pluck('laboratory')->all())->toBe([$lab])
        ->and(RegisterTpaRetailServicesAction::isTpaRetailStandaloneCoordination($coordinations['TRASLADO EN AMBULANCIA']))->toBeTrue()
        ->and(AffiliateClinicalServiceUsage::query()->count())->toBe($usageBefore)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->value('description'))->toContain('Sin consumo de cupo clínico');

    $medication = TelemedicinePatientMedications::query()->where('operation_coordination_service_id', $coordinations['MEDICAMENTOS']->id)->firstOrFail();

    expect($medication->telemedicine_doctor_id)->toBeNull()
        ->and($medication->medicine)->toBe('ACETAMINOFÉN 500 MG')
        ->and((bool) $medication->is_covered)->toBeFalse()
        ->and((int) $medication->quantity)->toBe(10);

    expect(retailCaseVisibleTo($case, retailTdgDoctorId()))->toBeFalse();
});

it('Telemedicina con el equipo TDG: el caso queda ASIGNADO al equipo y lo ve el médico TDG', function (): void {
    $actor = retailAnalyst();
    $patient = retailPatient();

    $result = RetailServiceRegistration::register($patient, [
        'services' => ['TELEMEDICINA'],
        'service_providers' => ['telemedicina' => 'equipo:TDG'],
        'reason' => 'Fiebre de 39 grados desde ayer',
    ], $actor);

    $case = $result['case']->refresh();
    $coordination = OperationCoordinationService::query()->findOrFail($result['coordination_ids'][0]);

    expect($case->status)->toBe('ASIGNADO')
        ->and((bool) $case->assigned_to_medical_team)->toBeTrue()
        ->and($case->medical_team_supplier_id)->toBeNull()
        ->and($case->managed_by)->toBe('TDG')
        ->and($case->reason)->toContain('Fiebre de 39 grados')
        ->and($coordination->assigned_provider_type)->toBe('equipo')
        ->and($coordination->supplier_service)->toBe('EQUIPO MÉDICO TDG')
        ->and($coordination->managed_by)->toBe('TDG')
        ->and($coordination->supplier_id)->toBeNull()
        ->and((bool) $coordination->assigned_to_supplier_by_tdg)->toBeFalse()
        ->and(retailCaseVisibleTo($case, retailTdgDoctorId()))->toBeTrue();
});

it('AMD con un proveedor de gestión: lo ve el equipo de ese proveedor y no TDG', function (): void {
    $actor = retailAnalyst();
    $patient = retailPatient();
    $team = retailManagedTeamSupplier();
    $lab = (string) TelemedicineListLaboratory::query()->orderBy('name')->value('name');

    $result = RetailServiceRegistration::register($patient, [
        'services' => ['AMD (ASISTENCIA MEDICA DOMICILIARIA)'],
        'service_providers' => ['amd_asistencia_medica_domiciliaria' => 'juridico:'.$team['supplier_id']],
        'labs' => [$lab],
        'labs_provider' => retailSupplierKey(),
        'reason' => 'Paciente encamado, requiere evaluación',
    ], $actor);

    $case = $result['case']->refresh();
    $coordinations = OperationCoordinationService::query()->whereIn('id', $result['coordination_ids'])->get()->keyBy('specific_service');
    $amd = $coordinations['AMD (ASISTENCIA MEDICA DOMICILIARIA)'];
    $labs = $coordinations['LABORATORIOS'];

    expect((bool) $case->assigned_to_medical_team)->toBeTrue()
        ->and((int) $case->medical_team_supplier_id)->toBe($team['supplier_id'])
        ->and(retailCaseVisibleTo($case, $team['doctor_id']))->toBeTrue()
        ->and(retailCaseVisibleTo($case, retailTdgDoctorId()))->toBeFalse();

    // La coordinación del equipo la gestiona el proveedor, igual que el caso: «Gestionado por» coincide con la cabecera.
    expect($amd->managed_by)->toBe($case->managed_by)
        ->and((int) $amd->supplier_id)->toBe($team['supplier_id'])
        ->and((bool) $amd->assigned_to_supplier_by_tdg)->toBeTrue()
        ->and($amd->assigned_to_supplier_by_tdg_by)->toBe($actor->name)
        ->and($amd->observations)->toStartWith(AssignCoordinationServiceToSupplier::OBSERVATION_PREFIX);

    // El laboratorio lo sigue coordinando TDG con su proveedor.
    expect($labs->managed_by)->toBe($patient->managed_by)
        ->and($labs->supplier_id)->toBe($patient->supplier_id)
        ->and((bool) $labs->assigned_to_supplier_by_tdg)->toBeFalse();

    // El portal de Operaciones del proveedor ve la de AMD (aunque sea no cubierta) y no la del laboratorio.
    $visibleInPortal = CoordinationServiceAccess::applyProviderCoordinationVisibilityScope(
        OperationCoordinationService::query()->whereIn('id', $result['coordination_ids']),
        $team['supplier_id'],
    )->pluck('id')->all();

    expect($visibleInPortal)->toBe([$amd->id]);
});

it('si falla un paso no queda nada a medias', function (): void {
    $actor = retailAnalyst();
    $patient = retailPatient();
    $casesBefore = TelemedicineCase::query()->withoutGlobalScopes()->count();

    expect(fn () => RetailServiceRegistration::register($patient, [
        'services' => ['APS'],
        'service_providers' => ['aps' => retailSupplierKey()],
        'labs' => ['LABORATORIO QUE NO EXISTE QA'],
        'labs_provider' => retailSupplierKey(),
    ], $actor))->toThrow(RetailServiceRegistrationException::class, 'no existe en el catálogo');

    expect(TelemedicineCase::query()->withoutGlobalScopes()->count())->toBe($casesBefore);
});

it('los casos solo de Operaciones sin médico no entran al pool TDG; con médico o equipo sí', function (): void {
    $patientId = TelemedicinePatient::query()->orderBy('id')->value('id');
    $insert = fn (array $attributes): int => DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => $patientId, 'code' => 'ZZRETAIL-'.uniqid(), 'managed_by' => 'TDG',
        'assigned_to_medical_team' => false, 'telemedicine_doctor_id' => null,
        'created_at' => now(), 'updated_at' => now(), ...$attributes,
    ]);
    $inTdgPool = fn (int $id): bool => TelemedicineCaseFilamentListQuery::constrainToTdgDoctorsCases(TelemedicineCase::query()->whereKey($id))->exists();

    expect($inTdgPool($insert(['status' => 'RETAIL'])))->toBeFalse()
        ->and($inTdgPool($insert(['status' => 'REGISTRO DIRECTO'])))->toBeFalse()
        ->and($inTdgPool($insert(['status' => 'ASIGNADO'])))->toBeTrue()
        ->and($inTdgPool($insert(['status' => 'RETAIL', 'telemedicine_doctor_id' => retailTdgDoctorId()])))->toBeTrue();
});

it('Gestionar servicio propone el proveedor asignado; un aliado corporativo no se precarga', function (): void {
    retailAnalyst();
    $supplierId = (int) Supplier::query()->orderBy('id')->value('id');

    $juridical = (new OperationCoordinationService)->forceFill(['assigned_provider_type' => 'juridico', 'assigned_supplier_id' => $supplierId]);
    $ally = (new OperationCoordinationService)->forceFill(['assigned_provider_type' => 'aliado', 'assigned_corporate_ally_id' => 1]);

    expect(CoordinationServiceItemsManager::assignedProviderDefaults($juridical))
        ->toMatchArray(['supplier_id' => $supplierId, 'doctor_nurse_id' => null, 'manage_quote_supplier_id' => $supplierId])
        ->and(CoordinationServiceItemsManager::assignedProviderDefaults($ally))->toBe([]);
});

it('la acción de la ficha abre la página de registro RETAIL', function (): void {
    $patient = retailPatient();
    $action = RegisterTpaRetailServicesAction::make()->record($patient);

    expect($action->getName())->toBe('register_tpa_retail_services')
        ->and($action->getUrl())->toBe(TelemedicinePatientResource::getUrl('retail', ['record' => $patient]));
});

it('la página registra desde el formulario y redirige al cuadro de servicios', function (): void {
    retailAnalyst();
    $patient = retailPatient();
    $supplierKey = retailSupplierKey();

    Livewire::test(RegisterRetailServices::class, ['record' => $patient->getKey()])
        ->assertOk()
        ->assertSee('Servicios principales')
        ->assertSee('Aún no hay nada seleccionado')
        ->set('data.services', ['URGEN CARE'])
        ->set('data.service_providers.urgen_care', $supplierKey)
        ->assertSee('El caso queda solo en Operaciones')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(OperationCoordinationService::query()
        ->where('telemedicine_patient_id', $patient->id)
        ->where('specific_service', 'URGEN CARE')
        ->where('assigned_provider_type', 'juridico')
        ->exists())->toBeTrue();
});

it('la página marca el proveedor faltante sin guardar nada', function (): void {
    retailAnalyst();
    $patient = retailPatient();
    $before = OperationCoordinationService::query()->count();

    Livewire::test(RegisterRetailServices::class, ['record' => $patient->getKey()])
        ->set('data.services', ['APS'])
        ->call('register')
        ->assertHasErrors(['data.service_providers.aps']);

    expect(OperationCoordinationService::query()->count())->toBe($before);
});
