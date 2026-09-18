<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PlanGenerators\Pages\PreAffiliationPopulation;
use App\Filament\Business\Resources\PlanGenerators\Pages\ViewPlanGenerator;
use App\Models\PlanGenerator;
use App\Models\PlanGeneratorPopulation;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationOptions;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Pre-afiliación desde el generador de planes.
 *
 * El analista aprueba la cotización, elige en una modal la cobertura con los
 * valores ya calculados en la matriz y, si es corporativo, carga la población
 * por importación antes de que se pueda crear la afiliación.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $this->analista = $analista;
    $this->plan = planConMatrizDeTarifas();
});

afterEach(function (): void {
    DB::rollBack();
});

/**
 * Plan generado con dos coberturas y dos rangos etarios, en PRE-APROBADO para
 * que la acción «Aprobar cotización» esté visible.
 */
function planConMatrizDeTarifas(): PlanGenerator
{
    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN PREAFILIACION PEST',
        'control_number' => 'PEST-PREAF-'.uniqid(),
        'client_data' => 'MERCAL DE PRUEBA',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '30',
        'status' => 'PRE-APROBADO',
        'quotation_page_count' => null,
        'plan_page_number' => null,
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => [
            ['column_key' => 'col-a', 'header_label' => 'PLAN I 5K'],
            ['column_key' => 'col-b', 'header_label' => 'IDEAL 10K'],
        ],
        'rows' => [],
        'rate_rows' => [
            'r1' => [
                'age_range_label' => '00 - 45',
                'population' => 10,
                'cells' => [
                    'col-a' => ['rate_amount' => 120.0],
                    'col-b' => ['rate_amount' => 255.0],
                ],
            ],
            'r2' => [
                'age_range_label' => '46 - 74',
                'population' => 20,
                'cells' => [
                    'col-a' => ['rate_amount' => 144.0],
                    'col-b' => ['rate_amount' => 310.0],
                ],
            ],
        ],
        'quotation_pages' => [],
    ]);

    return $plan->fresh();
}

it('arma una opción por cobertura y rango etario con los totales de la cotización', function (): void {
    $rows = PlanGeneratorPreAffiliationOptions::individualRows($this->plan);

    expect($rows)->toHaveCount(4);

    $primera = collect($rows)->firstWhere(fn (array $row): bool => $row['column_label'] === 'PLAN I 5K'
        && $row['age_range_label'] === '46 - 74');

    // Tarifa individual 144, población 20 del rango → anual 2.880.
    expect($primera['fee'])->toBe(144.0)
        ->and($primera['population'])->toBe(20)
        ->and($primera['subtotal_anual'])->toBe(2880.0)
        ->and($primera['subtotal_biannual'])->toBe(1440.0)
        ->and($primera['subtotal_quarterly'])->toBe(720.0);
});

it('arma una opción por cobertura con el total grupal para el corporativo', function (): void {
    $rows = PlanGeneratorPreAffiliationOptions::corporateRows($this->plan);

    expect($rows)->toHaveCount(2);

    // PLAN I 5K: 120×10 + 144×20 = 4.080 sobre 30 personas.
    $planI = collect($rows)->firstWhere('column_label', 'PLAN I 5K');

    expect($planI['population'])->toBe(30)
        ->and($planI['age_ranges'])->toBe(2)
        ->and($planI['subtotal_anual'])->toBe(4080.0)
        ->and($planI['subtotal_biannual'])->toBe(2040.0)
        ->and($planI['subtotal_quarterly'])->toBe(1020.0)
        ->and(round($planI['fee'], 2))->toBe(136.0);

    // IDEAL 10K: 255×10 + 310×20 = 8.750.
    expect(collect($rows)->firstWhere('column_label', 'IDEAL 10K')['subtotal_anual'])->toBe(8750.0);
});

it('descarta coberturas y rangos sin tarifa cargada', function (): void {
    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN SIN TARIFAS PEST',
        'control_number' => 'PEST-SINTAR-'.uniqid(),
        'client_data' => 'CLIENTE SIN TARIFAS',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '5',
        'status' => 'PRE-APROBADO',
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => [
            ['column_key' => 'col-a', 'header_label' => 'CON TARIFA'],
            ['column_key' => 'col-b', 'header_label' => 'SIN TARIFA'],
        ],
        'rows' => [],
        'rate_rows' => [
            'r1' => [
                'age_range_label' => '00 - 45',
                'population' => 5,
                'cells' => [
                    'col-a' => ['rate_amount' => 100.0],
                    'col-b' => ['rate_amount' => null],
                ],
            ],
        ],
        'quotation_pages' => [],
    ]);

    $plan = $plan->fresh();

    expect(PlanGeneratorPreAffiliationOptions::individualRows($plan))->toHaveCount(1)
        ->and(PlanGeneratorPreAffiliationOptions::corporateRows($plan))->toHaveCount(1)
        ->and(array_keys(PlanGeneratorPreAffiliationOptions::corporateOptions($plan)))->toBe(['col-a']);
});

it('la modal individual guarda en sesión la cobertura elegida y redirige a la afiliación', function (): void {
    $plan = $this->plan;

    $componente = Livewire::actingAs($this->analista)
        ->test(ViewPlanGenerator::class, ['record' => $plan->getKey()])
        ->assertOk()
        ->call('approveQuote', 'individual')
        ->assertActionMounted('chooseIndividualCoverage');

    $opcion = collect(PlanGeneratorPreAffiliationOptions::individualRows($plan))
        ->firstWhere(fn (array $row): bool => $row['column_label'] === 'IDEAL 10K' && $row['age_range_label'] === '00 - 45');

    $componente
        ->set('mountedActions.0.data.option_key', $opcion['key'])
        ->set('mountedActions.0.data.people', 3)
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Cobertura seleccionada')
        ->assertRedirect();

    $payload = PlanGeneratorPreAffiliationSession::get();

    expect($payload['type'])->toBe(PlanGeneratorPreAffiliationSession::TYPE_INDIVIDUAL)
        ->and($payload['data_records'])->toHaveCount(1)
        ->and($payload['selection']['column_label'])->toBe('IDEAL 10K')
        ->and($payload['selection']['age_range_label'])->toBe('00 - 45')
        ->and($payload['selection']['people'])->toBe(3);

    // Tarifa unitaria 255 × 3 personas de esta afiliación = 765 anual. NO las
    // 10 personas que el rango lleva cotizadas.
    $record = $payload['data_records'][0];

    expect($record['fee'])->toBe(255.0)
        ->and($record['total_persons'])->toBe(3)
        ->and($record['subtotal_anual'])->toBe(765.0)
        ->and($record['subtotal_biannual'])->toBe(382.5)
        ->and($record['subtotal_quarterly'])->toBe(191.25);

    // El formulario de afiliación resume solo lo elegido, no toda la matriz.
    expect(PlanGeneratorPreAffiliationSession::ratesSummary())
        ->toContain('IDEAL 10K (00 - 45)')
        ->not->toContain('PLAN I 5K');
});

it('la afiliación individual no arrastra la población cotizada del rango', function (): void {
    $plan = $this->plan;

    $opcion = collect(PlanGeneratorPreAffiliationOptions::individualRows($plan))
        ->firstWhere(fn (array $row): bool => $row['column_label'] === 'PLAN I 5K' && $row['age_range_label'] === '46 - 74');

    // El rango lleva 20 personas cotizadas, pero una afiliación individual es
    // un titular: el formulario abre un bloque de afiliado por persona y con la
    // población del rango la pantalla se vuelve inusable.
    expect($opcion['population'])->toBe(20);

    PlanGeneratorPreAffiliationSession::storeIndividual($plan, $opcion);

    $payload = PlanGeneratorPreAffiliationSession::get();

    expect($payload['total_persons'])->toBe(1)
        ->and($payload['data_records'][0]['total_persons'])->toBe(1)
        ->and($payload['data_records'][0]['subtotal_anual'])->toBe(144.0);

    // La descripción de la opción habla de tarifa unitaria y deja claro que la
    // población del rango es contexto, no el total de la afiliación.
    expect(PlanGeneratorPreAffiliationOptions::individualDescriptions($plan)[$opcion['key']])
        ->toContain('Tarifa individual anual US$ 144')
        ->toContain('20 personas cotizadas');
});

it('no admite afiliar más personas que las cotizadas en el rango', function (): void {
    $plan = $this->plan;

    $opcion = collect(PlanGeneratorPreAffiliationOptions::individualRows($plan))
        ->firstWhere(fn (array $row): bool => $row['column_label'] === 'PLAN I 5K' && $row['age_range_label'] === '00 - 45');

    Livewire::actingAs($this->analista)
        ->test(ViewPlanGenerator::class, ['record' => $plan->getKey()])
        ->call('approveQuote', 'individual')
        ->set('mountedActions.0.data.option_key', $opcion['key'])
        ->set('mountedActions.0.data.people', $opcion['population'] + 1)
        ->callMountedAction()
        ->assertHasActionErrors(['people']);
});

it('la modal individual exige elegir una cobertura', function (): void {
    Livewire::actingAs($this->analista)
        ->test(ViewPlanGenerator::class, ['record' => $this->plan->getKey()])
        ->call('approveQuote', 'individual')
        ->callMountedAction()
        ->assertHasActionErrors(['option_key' => 'required']);
});

it('la modal corporativa admite varias coberturas y lleva a cargar la población', function (): void {
    $plan = $this->plan;

    $componente = Livewire::actingAs($this->analista)
        ->test(ViewPlanGenerator::class, ['record' => $plan->getKey()])
        ->call('approveQuote', 'corporate')
        ->assertActionMounted('chooseCorporateCoverages');

    $componente
        ->set('mountedActions.0.data.column_keys', ['col-a', 'col-b'])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertRedirect(
            \App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource::getUrl(
                'pre-affiliation-population',
                ['record' => $plan->getKey()],
            ),
        );

    $payload = PlanGeneratorPreAffiliationSession::get();

    expect($payload['type'])->toBe(PlanGeneratorPreAffiliationSession::TYPE_CORPORATE)
        ->and($payload['data_records'])->toHaveCount(2)
        ->and($payload['total_persons'])->toBe(30)
        ->and(array_column($payload['data_records'], 'header_label'))->toBe(['PLAN I 5K', 'IDEAL 10K'])
        ->and(array_column($payload['data_records'], 'subtotal_anual'))->toBe([4080.0, 8750.0]);
});

it('una cotización sin tarifas no abre el selector de coberturas', function (): void {
    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN VACIO PEST',
        'control_number' => 'PEST-VACIO-'.uniqid(),
        'client_data' => 'CLIENTE VACIO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    Livewire::actingAs($this->analista)
        ->test(ViewPlanGenerator::class, ['record' => $plan->getKey()])
        ->call('approveQuote', 'individual')
        ->assertNotified('La cotización no tiene tarifas')
        ->assertActionNotMounted();
});

it('la página de población exige haber elegido coberturas antes', function (): void {
    PlanGeneratorPreAffiliationSession::forget();

    Livewire::actingAs($this->analista)
        ->test(PreAffiliationPopulation::class, ['record' => $this->plan->getKey()])
        ->assertNotified('Seleccione primero las coberturas')
        ->assertRedirect(
            \App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource::getUrl(
                'view',
                ['record' => $this->plan->getKey()],
            ),
        );
});

it('no deja continuar sin población y sí cuando el padrón está completo', function (): void {
    $plan = $this->plan;

    PlanGeneratorPreAffiliationSession::storeCorporate(
        $plan,
        PlanGeneratorPreAffiliationOptions::findCorporateRows($plan, ['col-a']),
    );

    expect(PlanGeneratorPopulationStatus::canContinue($plan))->toBeFalse()
        ->and(PlanGeneratorPopulationStatus::blockedReason($plan))
        ->toContain('Importe el padrón');

    PlanGeneratorPopulation::query()->create([
        'plan_generator_id' => $plan->getKey(),
        'last_name' => 'GARCIA',
        'first_name' => 'LUIS',
        'nro_identificacion' => '12345678',
        'birth_date' => '1990-01-01',
        'age' => '35',
    ]);

    expect(PlanGeneratorPopulationStatus::importedCount($plan->fresh()))->toBe(1)
        ->and(PlanGeneratorPopulationStatus::canContinue($plan->fresh()))->toBeTrue();
});

it('un import en curso bloquea el paso a la afiliación', function (): void {
    $plan = $this->plan;

    PlanGeneratorPreAffiliationSession::storeCorporate(
        $plan,
        PlanGeneratorPreAffiliationOptions::findCorporateRows($plan, ['col-a']),
    );

    PlanGeneratorPopulation::query()->create([
        'plan_generator_id' => $plan->getKey(),
        'last_name' => 'GARCIA',
        'first_name' => 'LUIS',
        'nro_identificacion' => '12345678',
        'birth_date' => '1990-01-01',
        'age' => '35',
    ]);

    $import = Import::query()->create([
        'user_id' => $this->analista->getKey(),
        'file_name' => 'padron.csv',
        'file_path' => 'padron.csv',
        'importer' => \App\Filament\Imports\PlanGeneratorPopulationImporter::class,
        'total_rows' => 30,
        'processed_rows' => 12,
        'successful_rows' => 12,
    ]);

    $plan->forceFill(['population_import_id' => $import->getKey()])->save();
    $plan = $plan->fresh();

    expect(PlanGeneratorPopulationStatus::isImportRunning($plan))->toBeTrue()
        ->and(PlanGeneratorPopulationStatus::canContinue($plan))->toBeFalse()
        ->and(PlanGeneratorPopulationStatus::blockedReason($plan))
        ->toContain('12 de 30');

    // Al cerrar el import se desbloquea.
    $import->forceFill(['completed_at' => now(), 'processed_rows' => 30, 'successful_rows' => 30])->save();

    expect(PlanGeneratorPopulationStatus::canContinue($plan->fresh()))->toBeTrue();
});

it('el listener deja constancia del import de población en el plan', function (): void {
    $plan = $this->plan;

    $import = Import::query()->create([
        'user_id' => $this->analista->getKey(),
        'file_name' => 'padron.csv',
        'file_path' => 'padron.csv',
        'importer' => \App\Filament\Imports\PlanGeneratorPopulationImporter::class,
        'total_rows' => 5,
    ]);

    event(new \Filament\Actions\Imports\Events\ImportStarted($import, [], [
        'plan_generator_id' => $plan->getKey(),
    ]));

    expect($plan->fresh()->population_import_id)->toBe($import->getKey());
});

it('la página de población expone importación, vaciado y el paso a la afiliación', function (): void {
    $plan = $this->plan;

    PlanGeneratorPreAffiliationSession::storeCorporate(
        $plan,
        PlanGeneratorPreAffiliationOptions::findCorporateRows($plan, ['col-a']),
    );

    PlanGeneratorPopulation::query()->create([
        'plan_generator_id' => $plan->getKey(),
        'last_name' => 'GARCIA',
        'first_name' => 'LUIS',
        'nro_identificacion' => '12345678',
        'birth_date' => '1990-01-01',
        'age' => '35',
    ]);

    Livewire::actingAs($this->analista)
        ->test(PreAffiliationPopulation::class, ['record' => $plan->getKey()])
        ->assertOk()
        ->assertActionExists(TestAction::make('importPopulation'))
        ->assertActionExists(TestAction::make('clearPopulation'))
        ->assertActionEnabled(TestAction::make('continueToAffiliation'))
        ->assertSee('GARCIA')
        ->assertSee('Población cargada')
        // Contraste con la población cotizada: 1 de 30.
        ->assertSee('de 30 cotizadas');
});

it('las páginas del generador se resuelven por su alias de Livewire', function (): void {
    // `Livewire::test(Clase::class)` instancia la clase directamente y no pasa
    // por el registro de alias; el navegador sí, porque `POST /livewire/update`
    // resuelve por nombre. Esto cubre que la página esté registrada en
    // `getPages()` y sea descubrible.
    //
    // Ojo: NO cubre el caso de `bootstrap/cache/filament` obsoleta. Sin cache,
    // Filament registra los componentes al vuelo y este test pasa igual; con
    // una cache vieja el navegador revienta con ComponentNotFoundException
    // aunque el GET funcione. Tras agregar una página o un recurso hay que
    // correr `php artisan filament:cache-components` (ver CLAUDE.md §2.1).
    $registry = app(\Livewire\Mechanisms\ComponentRegistry::class);

    $paginas = [
        'app.filament.business.resources.plan-generators.pages.pre-affiliation-population' => PreAffiliationPopulation::class,
        'app.filament.business.resources.plan-generators.pages.register-company' => \App\Filament\Business\Resources\PlanGenerators\Pages\RegisterCompany::class,
        'app.filament.business.resources.plan-generators.pages.view-plan-generator' => ViewPlanGenerator::class,
    ];

    foreach ($paginas as $alias => $clase) {
        expect($registry->getClass($alias))->toBe($clase);
    }
});

it('el flujo corporativo copia el padrón a los afiliados y bloquea si el import corre', function (): void {
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/CreateAffiliationCorporate.php');

    expect($create)
        ->toContain('protected function beforeCreate(): void')
        ->toContain('PlanGeneratorPopulationStatus::blockedReason')
        ->toContain('$this->halt();')
        ->toContain('copyPlanGeneratorPopulation')
        ->toContain('AffiliateCorporate::query()->insert($rows)')
        // Por lotes: un padrón corporativo pasa de mil filas con facilidad.
        ->toContain('chunkById(500');
});

it('el importador normaliza nombres y calcula la edad desde la fecha de nacimiento', function (): void {
    $plan = $this->plan;

    $import = Import::query()->create([
        'user_id' => $this->analista->getKey(),
        'file_name' => 'padron.csv',
        'file_path' => 'padron.csv',
        'importer' => \App\Filament\Imports\PlanGeneratorPopulationImporter::class,
        'total_rows' => 1,
    ]);

    $columnas = collect(\App\Filament\Imports\PlanGeneratorPopulationImporter::getColumns())
        ->mapWithKeys(fn ($columna): array => [$columna->getName() => $columna->getName()])
        ->all();

    $importador = $import->getImporter(
        columnMap: $columnas,
        options: ['plan_generator_id' => $plan->getKey()],
    );

    $importador([
        'last_name' => '  briceño silva ',
        'first_name' => 'albert jhosmel',
        'nro_identificacion' => ' 24345364 ',
        'birth_date' => '10/5/1996',
        // La columna «Edad» del archivo del cliente suele venir desactualizada.
        'age' => '18',
        'sex' => 'm',
        'phone' => '0414-4236335',
        'email' => 'albert@example.com',
        'condition_medical' => 'Sano',
        'initial_date' => '01/01/2025',
        'position_company' => 'Obrero',
        'address' => 'Valencia',
        'full_name_emergency' => 'albetzy briceño',
        'phone_emergency' => '0414-4002713',
    ]);

    $fila = PlanGeneratorPopulation::query()->where('import_id', $import->getKey())->firstOrFail();

    expect($fila->plan_generator_id)->toBe($plan->getKey())
        // Filament sobrescribe con el CSV crudo en `fillRecord()`; `beforeSave()`
        // es lo único que llega a la base.
        ->and($fila->last_name)->toBe('BRICEÑO SILVA')
        ->and($fila->first_name)->toBe('ALBERT JHOSMEL')
        ->and($fila->sex)->toBe('M')
        ->and($fila->nro_identificacion)->toBe('24345364')
        // La edad se recalcula y no se cree la del archivo.
        ->and($fila->age)->toBe((string) \Carbon\Carbon::parse('1996-05-10')->age)
        ->and($fila->age)->not->toBe('18')
        // La fecha se guarda tal cual, como `corporate_quote_data`: ambas caen
        // en la misma columna de `affiliate_corporates`.
        ->and($fila->birth_date)->toBe('10/5/1996');
});

it('no acusa filas fallidas antes de que el import arranque', function (): void {
    $plan = $this->plan;

    $import = Import::query()->create([
        'user_id' => $this->analista->getKey(),
        'file_name' => 'padron.csv',
        'file_path' => 'padron.csv',
        'importer' => \App\Filament\Imports\PlanGeneratorPopulationImporter::class,
        'total_rows' => 8,
    ]);

    $plan->forceFill(['population_import_id' => $import->getKey()])->save();
    $progreso = PlanGeneratorPopulationStatus::importProgress($plan->fresh());

    // `Import::getFailedRowsCount()` es `total - exitosas`, así que sin worker
    // levantado daba 8 fallidas de 8 filas que nadie había procesado.
    expect($import->getFailedRowsCount())->toBe(8)
        ->and($progreso['failed'])->toBe(0)
        ->and($progreso['queued'])->toBeTrue()
        ->and($progreso['completed'])->toBeFalse();

    expect(PlanGeneratorPopulationStatus::blockedReason($plan->fresh()))
        ->toContain('encolada y todavía no arrancó')
        ->toContain('queue:listen');
});

it('el importador de población apunta al plan generado y reusa el parseo de fechas', function (): void {
    $importer = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Imports/PlanGeneratorPopulationImporter.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Pages/PreAffiliationPopulation.php');

    expect($importer)
        ->toContain('protected static ?string $model = PlanGeneratorPopulation::class;')
        ->toContain("\$this->options['plan_generator_id']")
        ->toContain('CorporateQuoteBirthDateParser')
        ->toContain("'import_id' => \$this->import->getKey()");

    expect($page)
        ->toContain('->importer(PlanGeneratorPopulationImporter::class)')
        ->toContain("->csvDelimiter(';')")
        ->toContain('->job(ImportCsv::class)')
        ->toContain('->chunkSize(100)')
        ->toContain("'plan_generator_id' => \$this->getRecord()->getKey()");
});
