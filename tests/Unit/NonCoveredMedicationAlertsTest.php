<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\ManageCoordinationServiceItemsForm;
use App\Livewire\Operations\NonCoveredMedicationAlertModal;
use App\Models\OperationCoordinationService;
use App\Models\OperationNonCoveredMedicationAlert;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Operations\NonCoveredMedicationAlerts;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    NonCoveredMedicationAlerts::flushTableCache();
});

afterEach(fn () => DB::rollBack());

/**
 * Coordinación de medicamentos de un paciente real con el plan y la cobertura
 * indicados, y sus medicamentos. Todo se revierte al terminar el test.
 *
 * @param  list<array{medicine: string, is_covered?: bool, status?: string}>  $medications
 * @return array{coordination: OperationCoordinationService, medications: list<TelemedicinePatientMedications>}
 */
function nonCoveredAlertScenario(int $planId, ?int $coverageId, array $medications): array
{
    $patient = TelemedicinePatient::query()->findOrFail(247);
    $patient->forceFill(['plan_id' => $planId, 'coverage_id' => $coverageId])->saveQuietly();

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => 247,
        'telemedicine_case_id' => 1552,
        'specific_service' => 'MEDICAMENTOS',
        'status' => 'PENDIENTE',
        'patient' => 'PACIENTE DE PRUEBA AVISO',
        'ci_patient' => '12345678',
        'relationship_patient' => 'TITULAR',
        'contractor' => 'INDIVIDUAL',
        'symptoms_diagnosis' => 'Prueba',
        'created_by' => 'test',
        'reference_number' => 'TLM-TEST-AVISO',
    ]);

    $rows = [];

    foreach ($medications as $medication) {
        $rows[] = TelemedicinePatientMedications::query()->create([
            'telemedicine_patient_id' => 247,
            'telemedicine_case_id' => 1552,
            'operation_coordination_service_id' => $coordination->getKey(),
            'medicine' => $medication['medicine'],
            'indications' => '1 tableta cada 24 horas',
            'is_covered' => $medication['is_covered'] ?? false,
            'status' => $medication['status'] ?? 'PENDIENTE',
        ]);
    }

    return ['coordination' => $coordination, 'medications' => $rows];
}

function nonCoveredAlertAnalyst(int $id): User
{
    return User::factory()->make([
        'id' => $id,
        'name' => 'Analista '.$id,
        'departament' => ['OPERACIONES'],
        'supplier_id' => null,
        'is_proveedor_amd' => false,
    ]);
}

it('solo califica el Plan Especial con cobertura de 30K, 40K o 50K', function (string $name, int|float|string $amount, bool $expected): void {
    expect(NonCoveredMedicationAlerts::isSpecialPlanName($name) && NonCoveredMedicationAlerts::isQualifyingAmount($amount))
        ->toBe($expected);
})->with([
    'especial 30K' => ['PLAN ESPECIAL', '30000.00', true],
    'especial 40K' => ['PLAN ESPECIAL', 40000, true],
    'especial 50K' => ['PLAN ESPECIAL', 50000.0, true],
    'capemiac 40K' => ['PLAN ESPECIAL CAPEMIAC', '40000.00', true],
    'especial 20K' => ['PLAN ESPECIAL', '20000.00', false],
    'especial 5K' => ['PLAN ESPECIAL', '5000.00', false],
    'ideal 40K' => ['PLAN IDEAL', '40000.00', false],
    'monto vacío' => ['PLAN ESPECIAL', '', false],
]);

it('resuelve la cobertura del paciente contra los planes reales', function (int $planId, ?int $coverageId, ?float $amount): void {
    $patient = new TelemedicinePatient(['plan_id' => $planId, 'coverage_id' => $coverageId]);

    $result = NonCoveredMedicationAlerts::qualifyingCoverage($patient);

    expect($result['amount'] ?? null)->toBe($amount);
})->with([
    'plan especial 30K' => [3, 8, 30000.0],
    'plan especial 50K' => [3, 10, 50000.0],
    'capemiac 40K' => [32, 37, 40000.0],
    'plan especial 20K' => [3, 7, null],
    'plan especial sin cobertura' => [3, null, null],
    'cobertura de otro plan' => [1, 9, null],
]);

it('crea un solo aviso con los medicamentos no cubiertos y es idempotente', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg'],
        ['medicine' => 'Paracetamol 500 mg', 'is_covered' => true],
    ]);

    $alert = NonCoveredMedicationAlerts::registerForCoordination($coordination);
    $again = NonCoveredMedicationAlerts::registerForCoordination($coordination);

    expect($alert)->not->toBeNull()
        ->and($again->getKey())->toBe($alert->getKey())
        ->and($alert->medicationIds())->toBe([$medications[0]->id, $medications[1]->id])
        ->and((float) $alert->coverage_amount)->toBe(40000.0)
        ->and($alert->status)->toBe(OperationNonCoveredMedicationAlert::STATUS_PENDING)
        ->and(OperationNonCoveredMedicationAlert::query()->where('operation_coordination_service_id', $coordination->getKey())->count())->toBe(1);
});

it('no crea aviso si el plan no califica, si todo está cubierto o si no es de medicamentos', function (): void {
    $otherPlan = nonCoveredAlertScenario(3, 7, [['medicine' => 'Rosuvastatina 20 mg']]);
    expect(NonCoveredMedicationAlerts::registerForCoordination($otherPlan['coordination']))->toBeNull();

    $allCovered = nonCoveredAlertScenario(3, 9, [['medicine' => 'Paracetamol 500 mg', 'is_covered' => true]]);
    expect(NonCoveredMedicationAlerts::registerForCoordination($allCovered['coordination']))->toBeNull();

    $lab = nonCoveredAlertScenario(3, 9, [['medicine' => 'Rosuvastatina 20 mg']]);
    $lab['coordination']->forceFill(['specific_service' => 'LABORATORIOS'])->saveQuietly();
    expect(NonCoveredMedicationAlerts::registerForCoordination($lab['coordination']))->toBeNull();
});

it('muestra el aviso a cualquier analista con solo los medicamentos aún pendientes', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 10, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg'],
    ]);
    NonCoveredMedicationAlerts::registerForCoordination($coordination);

    $medications[1]->forceFill(['status' => 'EN GESTION'])->saveQuietly();

    $pending = collect(NonCoveredMedicationAlerts::pendingFor(nonCoveredAlertAnalyst(990001)))
        ->firstWhere('coordination_id', $coordination->getKey());

    expect($pending)->not->toBeNull()
        ->and($pending['coverage_label'])->toBe('USD 50.000')
        ->and($pending['plan_name'])->toBe('Plan Especial')
        ->and(array_column($pending['medications'], 'name'))->toBe(['Rosuvastatina 20 mg']);

    $medications[0]->forceFill(['status' => 'FINALIZADO'])->saveQuietly();

    expect(collect(NonCoveredMedicationAlerts::pendingFor(nonCoveredAlertAnalyst(990001)))->firstWhere('coordination_id', $coordination->getKey()))
        ->toBeNull();
});

it('no se muestra a usuarios de proveedores, AMD ni ATENMEDI', function (array $attributes): void {
    $user = User::factory()->make(['id' => 990009, 'departament' => ['OPERACIONES'], ...$attributes]);

    expect(NonCoveredMedicationAlerts::userCanReceive($user))->toBeFalse()
        ->and(NonCoveredMedicationAlerts::pendingFor($user))->toBe([]);
})->with([
    'usuario de proveedor' => [['supplier_id' => 15]],
    'proveedor AMD' => [['supplier_id' => null, 'is_proveedor_amd' => true]],
    'ATENMEDI' => [['supplier_id' => null, 'departament' => ['OPERACIONES', 'ATENMEDI']]],
]);

it('OK lo cierra solo para ese analista y lo deja en su campanita', function (): void {
    ['coordination' => $coordination] = nonCoveredAlertScenario(3, 8, [['medicine' => 'Rosuvastatina 20 mg']]);
    $alert = NonCoveredMedicationAlerts::registerForCoordination($coordination);
    NotificationFacade::fake();
    $analyst = nonCoveredAlertAnalyst(990001);

    NonCoveredMedicationAlerts::dismiss($alert->getKey(), $analyst);
    NonCoveredMedicationAlerts::dismiss($alert->getKey(), $analyst);

    $idsFor = fn (int $userId): array => array_column(NonCoveredMedicationAlerts::pendingFor(nonCoveredAlertAnalyst($userId)), 'id');

    expect($idsFor(990001))->not->toContain($alert->getKey())
        ->and($idsFor(990002))->toContain($alert->getKey())
        ->and($alert->refresh()->status)->toBe(OperationNonCoveredMedicationAlert::STATUS_PENDING);

    NotificationFacade::assertSentToTimes($analyst, DatabaseNotification::class, 1);
    NotificationFacade::assertSentTo($analyst, DatabaseNotification::class, function (DatabaseNotification $notification) use ($analyst): bool {
        $data = json_encode($notification->toDatabase($analyst));

        return str_contains($data, 'Ir a gestionar') && str_contains($data, 'manage-items');
    });
});

it('Ir a gestionar lo toma para todos y lleva a la cotización con los medicamentos marcados', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg'],
    ]);
    $alert = NonCoveredMedicationAlerts::registerForCoordination($coordination);

    $result = NonCoveredMedicationAlerts::claim($alert->getKey(), nonCoveredAlertAnalyst(990001), [(string) $medications[1]->id, 'x', 999999999]);

    expect($result['ok'])->toBeTrue()
        ->and($result['url'])->toContain('/operations/')
        ->toContain('/'.$coordination->getKey().'/manage-items')
        ->toContain('medicamentos='.$medications[1]->id)
        ->not->toContain((string) $medications[0]->id.',');

    $alert->refresh();

    expect($alert->status)->toBe(OperationNonCoveredMedicationAlert::STATUS_MANAGED)
        ->and($alert->managed_by_name)->toBe('Analista 990001')
        ->and($alert->managed_medication_ids)->toBe([$medications[1]->id])
        ->and(array_column(NonCoveredMedicationAlerts::pendingFor(nonCoveredAlertAnalyst(990002)), 'id'))->not->toContain($alert->getKey());

    $second = NonCoveredMedicationAlerts::claim($alert->getKey(), nonCoveredAlertAnalyst(990002), [$medications[0]->id]);

    expect($second['ok'])->toBeFalse()
        ->and($second['message'])->toContain('Analista 990001');
});

it('no muestra ni deja tomar el aviso si TDG asignó el servicio a un proveedor', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [['medicine' => 'Rosuvastatina 20 mg']]);
    $alert = NonCoveredMedicationAlerts::registerForCoordination($coordination);

    $coordination->forceFill(['supplier_id' => 15, 'assigned_to_supplier_by_tdg' => true])->saveQuietly();

    expect(array_column(NonCoveredMedicationAlerts::pendingFor(nonCoveredAlertAnalyst(990001)), 'id'))->not->toContain($alert->getKey())
        ->and(NonCoveredMedicationAlerts::claim($alert->getKey(), nonCoveredAlertAnalyst(990001), [$medications[0]->id])['ok'])->toBeFalse();
});

it('Ir a gestionar sin medicamentos marcados no toma el aviso', function (): void {
    ['coordination' => $coordination] = nonCoveredAlertScenario(3, 9, [['medicine' => 'Rosuvastatina 20 mg']]);
    $alert = NonCoveredMedicationAlerts::registerForCoordination($coordination);

    $result = NonCoveredMedicationAlerts::claim($alert->getKey(), nonCoveredAlertAnalyst(990001), []);

    expect($result['ok'])->toBeFalse()
        ->and($alert->refresh()->status)->toBe(OperationNonCoveredMedicationAlert::STATUS_PENDING);
});

it('el asistente solo acepta medicamentos gestionables de esa coordinación', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg', 'status' => 'FINALIZADO'],
    ]);
    $other = nonCoveredAlertScenario(3, 9, [['medicine' => 'Atorvastatina 20 mg']]);

    $this->actingAs(nonCoveredAlertAnalyst(990001));

    $raw = implode(',', ['abc', '0', $medications[0]->id, $medications[1]->id, $other['medications'][0]->id, $medications[0]->id]);

    expect(NonCoveredMedicationAlerts::preselectedKeys($coordination, $raw))->toBe(['medication:'.$medications[0]->id])
        ->and(NonCoveredMedicationAlerts::preselectedKeys($coordination, null))->toBe([])
        ->and(NonCoveredMedicationAlerts::preselectedKeys($coordination, ['1']))->toBe([]);
});

it('el aviso nace al guardar la consulta y el asistente arranca en «Cotización»', function (): void {
    $consultation = file_get_contents(app_path('Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'));
    $form = file_get_contents(app_path('Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php'));
    $page = file_get_contents(app_path('Filament/Operations/Resources/OperationCoordinationServices/Pages/ManageCoordinationServiceItems.php'));
    $panel = file_get_contents(app_path('Providers/Filament/OperationsPanelProvider.php'));

    expect($consultation)->toContain('NonCoveredMedicationAlerts::registerForCoordination($operationCoordinationService);')
        ->and($form)->toContain('->startOnStep(')
        ->toContain("Step::make('Cotización')")
        ->and(ManageCoordinationServiceItemsForm::QUOTE_STEP_LABEL)->toBe('Cotización')
        ->and($page)->toContain('preselectNonCoveredMedications()')
        ->toContain('callAfterStateUpdated()')
        ->and($panel)->toContain('filament.operations.hooks.non-covered-medication-alert');
});

it('la modal muestra los medicamentos marcados, OK a la izquierda e Ir a gestionar a la derecha', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg'],
    ]);
    NonCoveredMedicationAlerts::registerForCoordination($coordination);

    $superAdmin = User::factory()->make([
        'id' => 990003,
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
        'is_proveedor_amd' => false,
    ]);

    $component = Livewire::actingAs($superAdmin)->test(NonCoveredMedicationAlertModal::class);

    $alerts = collect($component->get('alerts'));
    $mine = $alerts->firstWhere('coordination_id', $coordination->getKey());

    expect($mine)->not->toBeNull();

    $html = $component->html();

    expect(strpos($html, '>'.PHP_EOL.'                        OK'))->toBeLessThan(strpos($html, 'Ir a gestionar'))
        ->and($html)->toContain('Medicamento')
        ->toContain('wire:poll.10s="refreshAlerts"');

    if ($alerts->first()['id'] === $mine['id']) {
        expect($component->get('selectedMedicationIds'))->toBe([(string) $medications[0]->id, (string) $medications[1]->id])
            ->and($html)->toContain('Rosuvastatina 20 mg')
            ->toContain('Omega 3 1000 mg');
    }
});

it('el asistente abre en «Cotización» con los medicamentos del aviso ya marcados', function (): void {
    ['coordination' => $coordination, 'medications' => $medications] = nonCoveredAlertScenario(3, 9, [
        ['medicine' => 'Rosuvastatina 20 mg'],
        ['medicine' => 'Omega 3 1000 mg'],
    ]);

    \Filament\Facades\Filament::setCurrentPanel('operations');

    $analyst = User::factory()->make([
        'id' => 990004,
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
        'is_proveedor_amd' => false,
    ]);

    $page = Livewire::withQueryParams([NonCoveredMedicationAlerts::QUERY_PARAMETER => (string) $medications[1]->id])
        ->actingAs($analyst)
        ->test(\App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems::class, [
            'record' => $coordination->getKey(),
        ]);

    expect($page->get('startsOnQuoteStep'))->toBeTrue()
        ->and($page->get('data.managed_service_item_keys'))->toBe(['medication:'.$medications[1]->id]);

    preg_match('/startStep:\s*(\d+)/', $page->html(), $startStep);

    /* Selección de ítems (1) → Cotización (2): «Orden de servicio» y «Proveedor no convenido» no aplican aún. */
    expect((int) ($startStep[1] ?? 0))->toBe(2)
        ->and($page->html())->toContain('Rosuvastatina 20 mg');

    $withoutAlert = Livewire::withQueryParams([])
        ->actingAs($analyst)
        ->test(\App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems::class, [
            'record' => $coordination->getKey(),
        ]);

    expect($withoutAlert->get('startsOnQuoteStep'))->toBeFalse()
        ->and($withoutAlert->get('data.managed_service_item_keys'))->toBe([]);
    preg_match('/startStep:\s*(\d+)/', $withoutAlert->html(), $defaultStart);
    expect((int) ($defaultStart[1] ?? 0))->toBe(1);
});
