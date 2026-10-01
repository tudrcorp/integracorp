<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Business\Resources\PlanGenerators\Pages\PreAffiliationPopulation;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\PlanGenerator;
use App\Models\PlanGeneratorPopulation;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorCatalogPublisher;
use App\Support\PlanGenerators\PlanGeneratorCoverageAssignment;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationOptions;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Ubicación manual del padrón en las coberturas de una pre-afiliación
 * corporativa del generador de planes.
 *
 * Matriz de prueba: «PLAN 3K» tarifa los dos rangos (0–45 y 46–74) y «PLAN
 * 10K» solo el de 0–45, para que haya edades que caben en una cobertura y no
 * en la otra.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    PlanGeneratorCoverageAssignment::flush();

    Filament::setCurrentPanel('business');

    $this->analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($this->analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $this->actingAs($this->analista);

    $this->plan = planDeDosCoberturas();

    PlanGeneratorPreAffiliationSession::storeCorporate(
        $this->plan,
        PlanGeneratorPreAffiliationOptions::findCorporateRows($this->plan, ['cov-3k', 'cov-10k']),
    );
});

afterEach(function (): void {
    DB::rollBack();
    PlanGeneratorPreAffiliationSession::forget();
    PlanGeneratorCoverageAssignment::flush();
});

/**
 * @param  array<string, array{age_range_label: string, population: int, cells: array<string, array{rate_amount: float|null}>}>|null  $rateRows
 */
function planDeDosCoberturas(?array $rateRows = null): PlanGenerator
{
    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN COBERTURAS PEST',
        'control_number' => 'PEST-COB-'.uniqid(),
        'client_data' => 'EMPRESA DE PRUEBA',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '4',
        'status' => 'PRE-APROBADO',
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => [
            ['column_key' => 'cov-3k', 'header_label' => 'PLAN 3K'],
            ['column_key' => 'cov-10k', 'header_label' => 'PLAN 10K'],
        ],
        'rows' => [],
        'rate_rows' => $rateRows ?? [
            'r1' => [
                'age_range_label' => '0 a 45 años',
                'population' => 3,
                'cells' => ['cov-3k' => ['rate_amount' => 200.0], 'cov-10k' => ['rate_amount' => 300.0]],
            ],
            'r2' => [
                'age_range_label' => '46 a 74 años',
                'population' => 1,
                'cells' => ['cov-3k' => ['rate_amount' => 250.0], 'cov-10k' => ['rate_amount' => null]],
            ],
        ],
        'quotation_pages' => [],
    ]);

    return $plan->fresh();
}

function personaDelPadron(PlanGenerator $plan, string $apellido, string $edad, ?string $cobertura = null): PlanGeneratorPopulation
{
    return PlanGeneratorPopulation::query()->create([
        'plan_generator_id' => $plan->getKey(),
        'column_key' => $cobertura,
        'last_name' => $apellido,
        'first_name' => 'PRUEBA',
        'nro_identificacion' => (string) random_int(10000000, 99999999),
        'birth_date' => '1990-01-01',
        'age' => $edad,
        'sex' => 'M',
    ]);
}

it('arma las coberturas elegidas con sus rangos tarifados', function (): void {
    $coverages = PlanGeneratorCoverageAssignment::coverages($this->plan, ['cov-3k', 'cov-10k']);

    expect(array_keys($coverages))->toBe(['cov-3k', 'cov-10k'])
        ->and($coverages['cov-3k']['ranges'])->toBe([
            ['label' => '0 a 45 años', 'min' => 0, 'max' => 45, 'fee' => 200.0],
            ['label' => '46 a 74 años', 'min' => 46, 'max' => 74, 'fee' => 250.0],
        ])
        // Sin tarifa en 46–74: ese rango no existe para el 10K.
        ->and(array_column($coverages['cov-10k']['ranges'], 'label'))->toBe(['0 a 45 años'])
        ->and($coverages['cov-3k']['defects'])->toBe([])
        ->and($coverages['cov-10k']['defects'])->toBe([]);
});

it('asigna solo a quien tiene una edad que cabe en la cobertura', function (): void {
    $joven = personaDelPadron($this->plan, 'JOVEN', '30');
    $mayor = personaDelPadron($this->plan, 'MAYOR', '60');

    $result = PlanGeneratorCoverageAssignment::assign($this->plan, [$joven->id, $mayor->id], 'cov-10k', 'PEST');

    expect($result['assigned'])->toBe(1)
        ->and($result['rejected'])->toHaveCount(1)
        ->and($result['rejected'][0]['name'])->toBe('MAYOR PRUEBA')
        ->and($result['rejected'][0]['reason'])->toContain('tiene 60 años')->toContain('0 a 45 años')
        ->and($joven->fresh()->column_key)->toBe('cov-10k')
        ->and($joven->fresh()->coverage_assigned_by)->toBe('PEST')
        ->and($joven->fresh()->coverage_assigned_at)->not->toBeNull()
        // Rechazado: no se toca.
        ->and($mayor->fresh()->column_key)->toBeNull();

    // La misma persona sí cabe en el 3K.
    expect(PlanGeneratorCoverageAssignment::assign($this->plan, [$mayor->id], 'cov-3k', 'PEST')['assigned'])->toBe(1)
        ->and($mayor->fresh()->column_key)->toBe('cov-3k');
});

it('respeta los límites del rango en sus dos extremos', function (): void {
    $limite = personaDelPadron($this->plan, 'LIMITE', '45');
    $pasado = personaDelPadron($this->plan, 'PASADO', '46');

    $result = PlanGeneratorCoverageAssignment::assign($this->plan, [$limite->id, $pasado->id], 'cov-10k', 'PEST');

    expect($result['assigned'])->toBe(1)
        ->and($limite->fresh()->column_key)->toBe('cov-10k')
        ->and($pasado->fresh()->column_key)->toBeNull();
});

it('no asigna a una cobertura que no se eligió para la pre-afiliación', function (): void {
    PlanGeneratorPreAffiliationSession::storeCorporate(
        $this->plan,
        PlanGeneratorPreAffiliationOptions::findCorporateRows($this->plan, ['cov-3k']),
    );

    $persona = personaDelPadron($this->plan, 'GARCIA', '30');

    expect(fn () => PlanGeneratorCoverageAssignment::assign($this->plan, [$persona->id], 'cov-10k', 'PEST'))
        ->toThrow(InvalidArgumentException::class, 'no forma parte de esta pre-afiliación');

    expect($persona->fresh()->column_key)->toBeNull();
});

it('ignora personas de otro plan generado', function (): void {
    $otroPlan = planDeDosCoberturas();
    $ajena = personaDelPadron($otroPlan, 'AJENA', '30');

    $result = PlanGeneratorCoverageAssignment::assign($this->plan, [$ajena->id], 'cov-3k', 'PEST');

    expect($result['assigned'])->toBe(0)
        ->and($result['rejected'][0]['reason'])->toContain('ya no están en el padrón')
        ->and($ajena->fresh()->column_key)->toBeNull();
});

it('rechaza a quien no tiene una edad válida en el padrón', function (): void {
    $sinEdad = personaDelPadron($this->plan, 'SINEDAD', 'abc');

    $result = PlanGeneratorCoverageAssignment::assign($this->plan, [$sinEdad->id], 'cov-3k', 'PEST');

    expect($result['assigned'])->toBe(0)
        ->and($result['rejected'][0]['reason'])->toContain('edad válida');

    expect(PlanGeneratorCoverageAssignment::blockedReason(PlanGeneratorCoverageAssignment::report($this->plan)))
        ->toContain('no tienen una edad válida')
        ->toContain('SINEDAD PRUEBA');
});

it('bloquea mientras quede alguien sin cobertura y libera cuando todos están ubicados', function (): void {
    $a = personaDelPadron($this->plan, 'UNO', '30');
    $b = personaDelPadron($this->plan, 'DOS', '50');

    expect(PlanGeneratorPopulationStatus::blockedReason($this->plan))->toContain('Faltan 2 persona(s)');

    PlanGeneratorCoverageAssignment::assign($this->plan, [$a->id], 'cov-10k', 'PEST');

    expect(PlanGeneratorPopulationStatus::blockedReason($this->plan))->toContain('Faltan 1 persona(s)');

    PlanGeneratorCoverageAssignment::assign($this->plan, [$b->id], 'cov-3k', 'PEST');

    expect(PlanGeneratorPopulationStatus::canContinue($this->plan))->toBeTrue();
});

it('reporta falla en la creación del plan cuando una edad no cabe en ninguna cobertura', function (): void {
    personaDelPadron($this->plan, 'ANCIANO', '80');

    $report = PlanGeneratorCoverageAssignment::report($this->plan);

    expect(PlanGeneratorCoverageAssignment::planFailure($report))
        ->toStartWith(PlanGeneratorCoverageAssignment::PLAN_FAILURE_PREFIX)
        ->toContain('ANCIANO PRUEBA (80 años)')
        ->toContain('PLAN 3K: 0 a 45 años · 46 a 74 años')
        ->and(PlanGeneratorPopulationStatus::canContinue($this->plan))->toBeFalse();
});

it('reporta falla en la creación del plan con rangos ilegibles o solapados', function (string $etiquetaUno, string $etiquetaDos, string $mensaje): void {
    $plan = planDeDosCoberturas([
        'r1' => ['age_range_label' => $etiquetaUno, 'population' => 1, 'cells' => ['cov-3k' => ['rate_amount' => 200.0]]],
        'r2' => ['age_range_label' => $etiquetaDos, 'population' => 1, 'cells' => ['cov-3k' => ['rate_amount' => 250.0]]],
    ]);

    PlanGeneratorPreAffiliationSession::storeCorporate($plan, PlanGeneratorPreAffiliationOptions::findCorporateRows($plan, ['cov-3k']));
    $persona = personaDelPadron($plan, 'GARCIA', '30');

    expect(PlanGeneratorCoverageAssignment::planFailure(PlanGeneratorCoverageAssignment::report($plan)))
        ->toStartWith(PlanGeneratorCoverageAssignment::PLAN_FAILURE_PREFIX)
        ->toContain($mensaje);

    // Con la cobertura mal armada no se asigna a nadie.
    expect(fn () => PlanGeneratorCoverageAssignment::assign($plan, [$persona->id], 'cov-3k', 'PEST'))
        ->toThrow(InvalidArgumentException::class, PlanGeneratorCoverageAssignment::PLAN_FAILURE_PREFIX);
})->with([
    'rango sin edad máxima' => ['0 a 45 años', '65+', 'no indica una edad mínima y una máxima'],
    'rangos solapados' => ['0 a 45 años', '40 a 70 años', 'se solapan'],
]);

it('marca como «no corresponde» una asignación que el plan dejó de admitir', function (): void {
    $persona = personaDelPadron($this->plan, 'GARCIA', '40', 'cov-10k');

    // Se edita la matriz: el 10K ahora solo tarifa hasta 35.
    PlanGeneratorPersistence::syncFromFormState($this->plan, [
        'columns' => [
            ['column_key' => 'cov-3k', 'header_label' => 'PLAN 3K'],
            ['column_key' => 'cov-10k', 'header_label' => 'PLAN 10K'],
        ],
        'rows' => [],
        'rate_rows' => [
            'r1' => ['age_range_label' => '0 a 35 años', 'population' => 1, 'cells' => ['cov-3k' => ['rate_amount' => 200.0], 'cov-10k' => ['rate_amount' => 300.0]]],
            'r2' => ['age_range_label' => '36 a 74 años', 'population' => 1, 'cells' => ['cov-3k' => ['rate_amount' => 250.0], 'cov-10k' => ['rate_amount' => null]]],
        ],
        'quotation_pages' => [],
    ]);

    $report = PlanGeneratorCoverageAssignment::report($this->plan->fresh());

    expect($report['misassigned'])->toHaveCount(1)
        ->and($report['placements'])->not->toHaveKey($persona->id)
        ->and(PlanGeneratorCoverageAssignment::blockedReason($report))->toContain('ya no les corresponde');
});

it('al continuar reemplaza lo cotizado por la población real de cada cobertura', function (): void {
    $a = personaDelPadron($this->plan, 'UNO', '30');
    $b = personaDelPadron($this->plan, 'DOS', '31');
    $c = personaDelPadron($this->plan, 'TRES', '60');

    PlanGeneratorCoverageAssignment::assign($this->plan, [$a->id, $b->id], 'cov-10k', 'PEST');
    PlanGeneratorCoverageAssignment::assign($this->plan, [$c->id], 'cov-3k', 'PEST');

    $report = PlanGeneratorCoverageAssignment::report($this->plan);
    PlanGeneratorPreAffiliationSession::storeCorporateAssignment($this->plan, $report);

    $payload = PlanGeneratorPreAffiliationSession::get();
    $records = collect($payload['data_records'])->keyBy('column_key');

    expect($payload['total_persons'])->toBe(3)
        // La población cotizada sigue a la vista para el contraste.
        ->and(PlanGeneratorPopulationStatus::declaredPopulation())->toBe(4)
        ->and(array_sum(array_column($payload['data_records'], 'total_persons')))->toBe(3)
        ->and($records['cov-3k']['total_persons'])->toBe(1)
        ->and($records['cov-3k']['subtotal_anual'])->toBe(250.0)
        ->and($records['cov-10k']['total_persons'])->toBe(2)
        ->and($records['cov-10k']['subtotal_anual'])->toBe(600.0)
        ->and(PlanGeneratorPreAffiliationSession::assignmentSignature())->toBe($report['signature']);

    // La firma cambia si alguien cambia de cobertura.
    PlanGeneratorCoverageAssignment::assign($this->plan, [$a->id], 'cov-3k', 'PEST');

    expect(PlanGeneratorCoverageAssignment::report($this->plan)['signature'])->not->toBe($report['signature']);
});

it('crea la afiliación con cada persona en su cobertura, rango y tarifa', function (): void {
    $a = personaDelPadron($this->plan, 'UNO', '30');
    $b = personaDelPadron($this->plan, 'DOS', '31');
    $c = personaDelPadron($this->plan, 'TRES', '60');
    $d = personaDelPadron($this->plan, 'CUATRO', '20');

    PlanGeneratorCoverageAssignment::assign($this->plan, [$a->id, $b->id], 'cov-10k', 'PEST');
    PlanGeneratorCoverageAssignment::assign($this->plan, [$c->id, $d->id], 'cov-3k', 'PEST');
    PlanGeneratorPreAffiliationSession::storeCorporateAssignment($this->plan, PlanGeneratorCoverageAssignment::report($this->plan));

    $catalog = PlanGeneratorCatalogPublisher::publish($this->plan, 'PEST');
    $record = afiliacionCorporativaDePrueba('TRIMESTRAL');

    ejecutarCreacionDesdeGenerador($record, $catalog);

    $planRows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $record->id)->get();
    $affiliates = AffiliateCorporate::query()->where('affiliation_corporate_id', $record->id)->get()->keyBy('last_name');

    $cov3k = $catalog['coverage_ids']['cov-3k'];
    $cov10k = $catalog['coverage_ids']['cov-10k'];

    // Una fila por (cobertura, rango) con la población real.
    expect($planRows)->toHaveCount(3)
        ->and((int) $planRows->firstWhere('coverage_id', $cov10k)->total_persons)->toBe(2)
        ->and((float) $planRows->firstWhere('coverage_id', $cov10k)->subtotal_anual)->toBe(600.0)
        ->and((int) $planRows->where('coverage_id', $cov3k)->sum('total_persons'))->toBe(2)
        ->and($affiliates)->toHaveCount(4)
        ->and((int) $affiliates['UNO']->coverage_id)->toBe($cov10k)
        ->and((float) $affiliates['UNO']->fee)->toBe(300.0)
        ->and((int) $affiliates['TRES']->coverage_id)->toBe($cov3k)
        ->and((float) $affiliates['TRES']->fee)->toBe(250.0)
        ->and((float) $affiliates['TRES']->subtotal_payment_frequency)->toBe(62.5)
        ->and((float) $affiliates['CUATRO']->fee)->toBe(200.0)
        ->and((int) $affiliates['CUATRO']->plan_id)->toBe((int) $catalog['plan']->getKey())
        ->and($affiliates['CUATRO']->status)->toBe('PRE-APROBADA')
        ->and((int) $affiliates['CUATRO']->created_by)->toBe((int) $this->analista->getKey());

    $record->refresh();

    expect((int) $record->poblation)->toBe(4)
        ->and((float) $record->fee_anual)->toBe(1050.0)
        ->and((float) $record->total_amount)->toBe(262.5)
        ->and(PlanGeneratorPreAffiliationSession::isActive())->toBeFalse();
});

it('no escribe nada si al crear queda alguien sin cobertura', function (): void {
    $a = personaDelPadron($this->plan, 'UNO', '30');
    personaDelPadron($this->plan, 'DOS', '31');

    PlanGeneratorCoverageAssignment::assign($this->plan, [$a->id], 'cov-10k', 'PEST');

    $catalog = PlanGeneratorCatalogPublisher::publish($this->plan, 'PEST');
    $record = afiliacionCorporativaDePrueba('ANUAL');

    expect(fn () => ejecutarCreacionDesdeGenerador($record, $catalog))->toThrow(Halt::class);

    expect(AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $record->id)->count())->toBe(0)
        ->and(AffiliateCorporate::query()->where('affiliation_corporate_id', $record->id)->count())->toBe(0)
        // La sesión se conserva para que el analista corrija y vuelva.
        ->and(PlanGeneratorPreAffiliationSession::isActive())->toBeTrue();
});

it('la página permite asignar en lote y por fila, y habilita continuar al terminar', function (): void {
    $joven = personaDelPadron($this->plan, 'JOVEN', '30');
    $mayor = personaDelPadron($this->plan, 'MAYOR', '60');

    $page = Livewire::test(PreAffiliationPopulation::class, ['record' => $this->plan->getKey()])
        ->assertOk()
        ->assertSee('Sin asignar')
        ->assertSee('Admitida en')
        ->assertActionDisabled(TestAction::make('continueToAffiliation'));

    // En lote al 10K: el joven entra, el mayor no.
    $page->selectTableRecords([$joven->getKey(), $mayor->getKey()])
        ->mountAction(TestAction::make('assignCoverageBulk')->table()->bulk())
        ->set('mountedActions.0.data.column_key', 'cov-10k')
        ->callMountedAction()
        ->assertHasNoErrors()
        // Cada aserción consume las notificaciones de la sesión: se verifica
        // la del rechazo y el resto por la base.
        ->assertNotified('1 persona(s) no se asignaron a PLAN 10K');

    expect($joven->fresh()->column_key)->toBe('cov-10k')
        ->and($mayor->fresh()->column_key)->toBeNull();

    // Por fila al 3K.
    PlanGeneratorCoverageAssignment::flush();
    $page->mountAction(TestAction::make('assignCoverage')->table($mayor))
        ->set('mountedActions.0.data.column_key', 'cov-3k')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Cobertura asignada');

    expect($mayor->fresh()->column_key)->toBe('cov-3k');

    PlanGeneratorCoverageAssignment::flush();
    $page->call('$refresh')
        ->assertActionEnabled(TestAction::make('continueToAffiliation'));
});

it('la acción por fila solo ofrece las coberturas que admiten la edad', function (): void {
    $mayor = personaDelPadron($this->plan, 'MAYOR', '60');

    $options = PlanGeneratorCoverageAssignment::coverageOptions($this->plan, 60, filterByAge: true);

    expect(array_keys($options))->toBe(['cov-3k'])
        ->and($options['cov-3k'])->toContain('PLAN 3K');

    expect(PlanGeneratorCoverageAssignment::coverageOptions($this->plan, 90, filterByAge: true))->toBe([]);

    Livewire::test(PreAffiliationPopulation::class, ['record' => $this->plan->getKey()])
        ->assertTableActionEnabled('assignCoverage', $mayor);
});

function afiliacionCorporativaDePrueba(string $frecuencia): AffiliationCorporate
{
    $record = new AffiliationCorporate;
    $record->forceFill([
        'code' => 'PEST-AFC-'.uniqid(),
        'corporate_quote_id' => 0,
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'name_corporate' => 'EMPRESA DE PRUEBA',
        'rif' => 'J-00000000-0',
        'address' => 'CARACAS',
        'city_id' => 1,
        'country_id' => 1,
        'region_id' => 1,
        'phone' => '02120000000',
        'email' => 'pest@example.com',
        'full_name_contact' => 'CONTACTO PEST',
        'nro_identificacion_contact' => '1234567',
        'phone_contact' => '04120000000',
        'email_contact' => 'contacto@example.com',
        'payment_frequency' => $frecuencia,
        'fee_anual' => 0,
        'total_amount' => 0,
        'created_by' => 'PEST',
        'status' => 'PRE-APROBADA',
    ])->saveQuietly();

    return $record;
}

/**
 * Corre el paso de `afterCreate()` del generador sobre una afiliación ya
 * creada, con el catálogo recién publicado, como lo hace `create()`.
 *
 * @param  array{plan: \App\Models\Plan, coverage_ids: array<string, int>, age_range_ids: array<string, int>}  $catalog
 */
function ejecutarCreacionDesdeGenerador(AffiliationCorporate $record, array $catalog): void
{
    $page = new CreateAffiliationCorporate;
    $reflection = new ReflectionClass($page);

    $catalogProperty = $reflection->getProperty('planGeneratorCatalog');
    $catalogProperty->setValue($page, $catalog);

    $method = $reflection->getMethod('afterCreateFromPlanGenerator');
    $method->invoke($page, $record);
}
