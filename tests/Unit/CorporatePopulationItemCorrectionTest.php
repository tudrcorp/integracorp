<?php

declare(strict_types=1);

use App\Jobs\SyncCorporatePopulationItemsJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\Population\PopulationColumnMapping;
use App\Support\AffiliationCorporates\Population\PopulationFieldComparison as Field;
use App\Support\AffiliationCorporates\Population\PopulationFileReader;
use App\Support\AffiliationCorporates\Population\PopulationItemCorrection;
use App\Support\AffiliationCorporates\Population\PopulationItemExplainer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Escriben en la base y en disco: transacción revertida, disco, correo y cola simulados.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('local');
    Mail::fake();
    Queue::fake();
    Illuminate\Support\Facades\Bus::fake([App\Jobs\SendNotificacionWhatsApp::class]);
});

afterEach(function (): void {
    DB::rollBack();
});

function picActor(): User
{
    return User::factory()->make(['id' => 987910, 'name' => 'Analista Corrección Pest']);
}

/**
 * Afiliación sintética con un plan de 1 a 99 años y fichas con los defectos reales de MAPFRE:
 *  - LUIS: nombres y apellidos cruzados (coincide por cédula).
 *  - SOFIA: menor con número de relleno en la base (coincide por fecha y nombre).
 *  - ROSA: solo el nombre coincide; cédula y fecha distintas → Revisar.
 *  - ELENA y su copia con cédula con un dígito de más → Duplicado en la base.
 *
 * @return array{owner: AffiliationCorporate, row: AfilliationCorporatePlan, luis: AffiliateCorporate, sofia: AffiliateCorporate, rosa: AffiliateCorporate, elena: AffiliateCorporate, copy: AffiliateCorporate}
 */
function picFixture(): array
{
    $sourceRow = AfilliationCorporatePlan::query()->firstOrFail();
    $range = new App\Models\AgeRange;
    $range->forceFill(['plan_id' => 0, 'range' => '1 a 99', 'age_init' => 1, 'age_end' => 99, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();
    $sourceOwner = AffiliationCorporate::query()->findOrFail($sourceRow->affiliation_corporate_id);

    $owner = $sourceOwner->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA CORRECCIONES PEST', 'poblation' => 5, 'fee_anual' => 500, 'total_amount' => 125, 'payment_frequency' => 'TRIMESTRAL'])->save();

    $row = $sourceRow->replicate();
    $row->forceFill(['affiliation_corporate_id' => $owner->id, 'age_range_id' => $range->id, 'fee' => 100, 'total_persons' => 5])->save();

    $make = function (string $first, string $last, string $doc, ?string $birth, ?int $age, ?string $sex = null) use ($owner, $row): AffiliateCorporate {
        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => $first, 'last_name' => $last,
            'nro_identificacion' => $doc, 'birth_date' => $birth, 'age' => $age, 'sex' => $sex, 'condition_medical' => 'SANO',
            'plan_id' => $row->plan_id, 'coverage_id' => $row->coverage_id, 'fee' => 100, 'subtotal_anual' => 100,
            'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO', 'created_by' => 1,
        ])->save();

        return $affiliate;
    };

    return [
        'owner' => $owner,
        'row' => $row,
        'luis' => $make('QUINTERO PESTANO', 'LUIS ALBERTO', '71111111', '1/1/1980', 46, 'M'),
        'sofia' => $make('SOFIA VALERIA', 'QUINTERO PESTANO', '00000043', '5/5/2015', 11),
        'rosa' => $make('ROSA AMELIA', 'PESTANA ZURITA', '73333333', '3/3/1970', 56),
        'elena' => $make('ELENA', 'PESTANO VILAR', '75320349', null, 44),
        'copy' => $make('PESTANO ELENA', 'KARINA', '753203491', '8/2/1982', 44),
    ];
}

function picCsv(string $extra = ''): string
{
    return implode("\n", array_filter([
        'CODIGO_DOCUMENTO;NOMBRE_RIESGO;1ER_APELLIDO_RIESGO;2DO_APELLIDO_RIESGO;NACIMIENTO;SEXO;PARENTESCO',
        '71111111;LUIS ALBERTO;QUINTERO;PESTANO;01/01/1980;M;TI',
        '071111111-15;SOFIA VALERIA;QUINTERO;PESTANO;05/05/2015;F;HI',
        '74444444;ROSA AMELIA;PESTANA;ZURITA;04/04/1971;F;CO',
        '75320349;ELENA;PESTANO;VILAR;08/02/1982;F;TI',
        $extra,
    ]));
}

function picUpdate(AffiliationCorporate $owner, ?string $content = null): CorporatePopulationUpdate
{
    $path = 'corporate-population/'.$owner->id.'/pest-'.Str::random(6).'.csv';
    Storage::disk('local')->put($path, $content ?? picCsv());
    $headers = PopulationFileReader::headers(Storage::disk('local')->path($path), 'csv');

    $update = CorporatePopulationUpdate::query()->create([
        'public_id' => (string) Str::uuid(),
        'affiliation_corporate_id' => $owner->id,
        'status' => CorporatePopulationUpdate::STATUS_ANALYZING,
        'mode' => 'FULL',
        'file_disk' => 'local',
        'file_path' => $path,
        'file_name' => 'poblacion.csv',
        'file_sha1' => sha1('x'),
        'file_format' => 'csv',
        'mapping' => PopulationColumnMapping::validate(PopulationColumnMapping::guess($headers), $headers),
        'analyzed_by' => 987910,
        'analyzed_by_name' => 'Analista Corrección Pest',
    ]);

    return PopulationUpdateAnalyzer::analyze($update);
}

function picItemFor(CorporatePopulationUpdate $update, AffiliateCorporate $affiliate): Item
{
    return Item::query()->where('corporate_population_update_id', $update->id)->where('affiliate_corporate_id', $affiliate->id)->firstOrFail();
}

/**
 * @return array<string, string|null>
 */
function picFileValues(Item $item): array
{
    return Field::fileValues((array) $item->file_data);
}

it('compara campo por campo: nombres cruzados, cédula de relleno, fecha y eñe dañada', function (): void {
    $swapped = Field::compare(
        ['document' => '7873895', 'first_name' => 'EDGAR JESUS', 'last_name' => 'CASTRO LUGO', 'birth_date' => '1967-02-05', 'sex' => 'MASCULINO', 'relationship' => 'TITULAR'],
        ['document' => '7873895', 'first_name' => 'CASTRO LUGO', 'last_name' => 'EDGAR JESUS', 'birth_date' => '1967-02-05', 'sex' => 'MASCULINO', 'relationship' => null],
    );

    expect($swapped['first_name']['status'])->toBe(Field::SWAPPED)
        ->and($swapped['first_name']['counted'])->toBeTrue()
        ->and($swapped['document']['status'])->toBe(Field::SAME)
        ->and($swapped['relationship']['status'])->toBe(Field::MISSING_BASE)
        ->and($swapped['relationship']['counted'])->toBeFalse();

    $minor = Field::compare(
        ['document' => '010397227-19', 'first_name' => 'ENDERLIN LUZ', 'last_name' => 'SALAS ACOSTA', 'birth_date' => '2019-09-08', 'sex' => null, 'relationship' => null],
        ['document' => '00000043', 'first_name' => 'ENDERLIN LUZ', 'last_name' => 'SALAS ACOSTA', 'birth_date' => '2019-08-09', 'sex' => null, 'relationship' => null],
    );

    expect($minor['document']['status'])->toBe(Field::FILLER)
        ->and($minor['birth_date']['status'])->toBe(Field::DIFFERENT)
        ->and($minor['birth_date']['note'])->toContain('día y mes invertidos');

    $format = Field::compare(
        ['document' => '1', 'first_name' => 'MERCEDES', 'last_name' => 'GONZALEZ CEDE?O', 'birth_date' => null, 'sex' => null, 'relationship' => null],
        ['document' => '1', 'first_name' => 'MERCEDES', 'last_name' => 'GONZALEZ CEDEÑO', 'birth_date' => null, 'sex' => null, 'relationship' => null],
    );

    expect($format['last_name']['status'])->toBe(Field::FORMAT)
        ->and($format['last_name']['counted'])->toBeFalse();
});

it('el análisis guarda las diferencias de cada coincidencia y las cuenta aparte', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);

    $luis = picItemFor($update, $f['luis']);
    $sofia = picItemFor($update, $f['sofia']);

    expect($luis->action)->toBe(Item::ACTION_MATCH)
        ->and($luis->details['differences'])->toBe(['first_name', 'last_name'])
        ->and($sofia->action)->toBe(Item::ACTION_MATCH)
        ->and($sofia->details['differences'])->toBe(['document'])
        ->and(picItemFor($update, $f['elena'])->details['differences'])->toBe(['birth_date'])
        ->and($update->summary['match_with_differences'])->toBe(3)
        ->and(picItemFor($update, $f['rosa'])->action)->toBe(Item::ACTION_REVIEW)
        ->and(picItemFor($update, $f['copy'])->action)->toBe(Item::ACTION_DUPLICATE_BASE);
});

it('corrige una ficha en Revisar, la vuelve a cruzar y queda en Coincide sin tocar la tarifa', function (): void {
    $f = picFixture();
    $patient = TelemedicinePatient::query()->firstOrFail()->replicate();
    $patient->forceFill(['afilliation_corporate_id' => $f['owner']->id, 'afilliation_id' => null, 'type_affiliation' => 'CORPORATIVO', 'code_affiliation' => $f['owner']->code, 'nro_identificacion' => '73333333', 'status_affiliation' => 'ACTIVO'])->saveQuietly();

    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['rosa']);
    $fingerprint = PopulationItemCorrection::fingerprint($f['rosa']->fresh());

    expect(fn () => PopulationItemCorrection::correct($item, picFileValues($item), 'Verificado con la cédula enviada por la empresa.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'Confirme primero');

    $result = PopulationItemCorrection::correct($item, picFileValues($item), 'Verificado con la cédula enviada por la empresa.', picActor(), $fingerprint, true);
    $rosa = $f['rosa']->fresh();

    expect($result['item']->action)->toBe(Item::ACTION_MATCH)
        ->and($result['item']->details['differences'])->toBe([])
        ->and($result['changed'])->toContain('nro_identificacion', 'birth_date', 'sex', 'relationship')
        ->and($rosa->nro_identificacion)->toBe('74444444')
        ->and($rosa->birth_date)->toBe('04/04/1971')
        ->and($rosa->relationship)->toBe('ESPOSA')
        ->and((float) $rosa->fee)->toBe(100.0)
        ->and((int) $rosa->age)->toBe(56)
        ->and($patient->fresh()->nro_identificacion)->toBe('74444444')
        ->and($result['item']->details['corrections'][0]['before']['nro_identificacion'])->toBe('73333333')
        ->and($update->fresh()->summary['counts'][Item::ACTION_REVIEW] ?? 0)->toBe(0)
        ->and(DB::table('logs')->where('action', 'AUDIT_CORPORATE_POPULATION_ITEM_CORRECTED')->where('response', 'like', '%"item_id":'.$item->id.'%')->exists())->toBeTrue();
});

it('no guarda si la ficha cambió mientras el analista la revisaba ni con datos imposibles', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['luis']);
    $fingerprint = PopulationItemCorrection::fingerprint($f['luis']->fresh());
    $values = picFileValues($item);

    $f['luis']->forceFill(['sex' => 'F'])->save();
    expect(fn () => PopulationItemCorrection::correct($item, $values, 'Corrección de nombres cruzados.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'Alguien modificó esta ficha');

    $fingerprint = PopulationItemCorrection::fingerprint($f['luis']->fresh());

    expect(fn () => PopulationItemCorrection::correct($item, [...$values, 'birth_date' => now()->addDay()->toDateString()], 'Fecha futura por error.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'no es posible')
        ->and(fn () => PopulationItemCorrection::correct($item, [...$values, 'sex' => 'MASCULINO', 'relationship' => 'ESPOSA'], 'Parentesco contradictorio.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'no corresponde al sexo')
        ->and(fn () => PopulationItemCorrection::correct($item, [...$values, 'document' => '73333333'], 'Cédula de otra persona.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'ya la tiene')
        ->and(fn () => PopulationItemCorrection::correct($item, $values, 'corto', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'al menos')
        ->and(fn () => PopulationItemCorrection::correct($item, [...$values, 'last_name' => 'URRIBARRI DE PI?A'], 'Eñe dañada en el archivo.', picActor(), $fingerprint))
        ->toThrow(RuntimeException::class, 'carácter dañado');

    expect($f['luis']->fresh()->first_name)->toBe('QUINTERO PESTANO');
});

it('deja como diferencia aceptada lo que el analista decide mantener y la tarifa avisa del cambio de rango', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['sofia']);

    $values = [...picFileValues($item), 'document' => '00000043'];
    $result = PopulationItemCorrection::correct($item, $values, 'La empresa confirmó que la cédula de menor aún no está emitida.', picActor(), PopulationItemCorrection::fingerprint($f['sofia']->fresh()));

    expect($result['item']->action)->toBe(Item::ACTION_MATCH)
        ->and($result['item']->details['differences'])->toBe([])
        ->and(array_keys($result['item']->details['accepted']))->toBe(['document'])
        ->and($update->fresh()->summary['match_with_differences'])->toBe(2);

    $explanation = PopulationItemExplainer::explain($result['item']->fresh(), $f['sofia']->fresh(), null, CorporateAffiliatePlanSynchronizer::planRowsFor($f['owner']), 5);
    expect($explanation['headline'])->toContain('aceptadas')
        ->and($explanation['history'])->not->toBeEmpty();

    $warnings = PopulationItemCorrection::warnings(PopulationItemCorrection::normalize([...$values, 'birth_date' => now()->subMonths(3)->toDateString()]), $f['sofia']->fresh(), $f['owner']);
    expect(implode(' ', $warnings))->toContain('La tarifa no cambia ahora');
});

it('deshace la última corrección solo si nadie tocó la ficha después', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['luis']);

    PopulationItemCorrection::correct($item, picFileValues($item), 'Nombres y apellidos cruzados en la ficha.', picActor(), PopulationItemCorrection::fingerprint($f['luis']->fresh()));
    expect($f['luis']->fresh()->first_name)->toBe('LUIS ALBERTO');

    $undone = PopulationItemCorrection::undoLast($item->fresh(), 'Se corrigió la ficha equivocada.', picActor());
    expect($f['luis']->fresh()->first_name)->toBe('QUINTERO PESTANO')
        ->and($undone->details['differences'])->toBe(['first_name', 'last_name'])
        ->and(PopulationItemCorrection::lastOpenCorrection($undone->details['corrections']))->toBeNull();

    PopulationItemCorrection::correct($undone->fresh(), picFileValues($undone), 'Nombres y apellidos cruzados en la ficha.', picActor(), PopulationItemCorrection::fingerprint($f['luis']->fresh()));
    $f['luis']->fresh()->forceFill(['first_name' => 'LUIS'])->save();

    expect(fn () => PopulationItemCorrection::undoLast($item->fresh(), 'Intento de deshacer tardío.', picActor()))
        ->toThrow(RuntimeException::class, 'cambió después de la corrección');
});

it('«No es la misma persona» convierte la fila en alta y propone de baja la ficha que sobra', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['rosa']);

    $split = PopulationItemCorrection::notSamePerson($item, 'Son hermanas: la empresa confirmó cédulas distintas.', picActor());
    $freed = Item::query()->where('corporate_population_update_id', $update->id)->where('affiliate_corporate_id', $f['rosa']->id)->firstOrFail();

    expect($split->action)->toBe(Item::ACTION_ADD)
        ->and($split->affiliate_corporate_id)->toBeNull()
        ->and($split->selected)->toBeTrue()
        ->and($split->details['not_same_as']['affiliate_id'])->toBe((int) $f['rosa']->id)
        ->and($freed->action)->toBe(Item::ACTION_DEACTIVATE)
        ->and($freed->selected)->toBeTrue()
        ->and($update->fresh()->summary['counts'][Item::ACTION_ADD])->toBe(1);
});

it('resuelve el duplicado pasando a la ficha que queda la fecha que le faltaba, y aplica la carga', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['copy']);

    expect($item->selected)->toBeFalse();

    $result = PopulationItemCorrection::resolveDuplicate($item, ['birth_date', 'sex'], 'Misma persona cargada dos veces; vale la cédula del archivo.', picActor());

    expect($result['copied'])->toBe(['birth_date'])
        ->and($result['item']->selected)->toBeTrue()
        ->and($f['elena']->fresh()->birth_date)->toBe('08/02/1982')
        ->and(picItemFor($update, $f['elena'])->details['corrections'][0]['note'])->toContain('duplicado #'.$f['copy']->id);

    PopulationUpdateApplier::approve($update->fresh(), picActor(), false);
    $applied = PopulationUpdateApplier::apply($update->fresh());

    expect($applied->status)->toBe(CorporatePopulationUpdate::STATUS_APPLIED)
        ->and($f['copy']->fresh()->status)->toBe('INACTIVO')
        ->and($f['elena']->fresh()->status)->toBe('ACTIVO');
});

it('«No es un duplicado» lo deja como baja propuesta por no estar en el archivo', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);

    $item = PopulationItemCorrection::notDuplicate(picItemFor($update, $f['copy']), 'Es otra persona: la madre de la titular.', picActor());

    expect($item->action)->toBe(Item::ACTION_DEACTIVATE)
        ->and($item->selected)->toBeTrue()
        ->and(PopulationItemExplainer::explain($item, $f['copy']->fresh(), null, collect(), 5)['rule'])->toContain('no es un duplicado');
});

it('sincroniza en lote solo los datos elegidos y no da por buenas las demás diferencias', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $items = Item::query()->where('corporate_population_update_id', $update->id)->whereIn('affiliate_corporate_id', [$f['luis']->id, $f['sofia']->id])->get();

    $result = PopulationItemCorrection::syncWithFile($items, ['document'], 'El archivo del 04/11 trae las cédulas oficiales.', picActor());

    expect($result)->toMatchArray(['updated' => 1, 'unchanged' => 1, 'failed' => []])
        ->and($f['sofia']->fresh()->nro_identificacion)->toBe('071111111-15')
        ->and($f['luis']->fresh()->first_name)->toBe('QUINTERO PESTANO')
        ->and(picItemFor($update, $f['luis'])->details['differences'])->toBe(['first_name', 'last_name'])
        ->and(picItemFor($update, $f['luis'])->details['accepted'] ?? [])->toBe([]);
});

it('el trabajo en cola sincroniza, avisa al analista y libera la marca de ocupado', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $analyst = User::query()->firstOrFail();
    $ids = Item::query()->where('corporate_population_update_id', $update->id)->where('action', Item::ACTION_MATCH)->pluck('id')->all();

    Cache::put(SyncCorporatePopulationItemsJob::busyKey($update->id), 1, 60);
    (new SyncCorporatePopulationItemsJob($update->id, $ids, ['names', 'document'], 'Sincronización completa con el archivo oficial.', (int) $analyst->id))->handle();

    expect(Cache::has(SyncCorporatePopulationItemsJob::busyKey($update->id)))->toBeFalse()
        ->and($f['luis']->fresh()->first_name)->toBe('LUIS ALBERTO')
        ->and($f['sofia']->fresh()->nro_identificacion)->toBe('071111111-15')
        ->and($update->fresh()->summary['match_with_differences'])->toBe(1);

    Queue::assertPushed(Illuminate\Notifications\SendQueuedNotifications::class, fn ($job): bool => str_contains((string) ($job->notification->data['title'] ?? ''), 'Sincronización con el archivo terminada'));
});

it('vuelve a evaluar un alta sin plan cuando la afiliación ya tiene el rango de edad', function (): void {
    $f = picFixture();
    $baby = now()->subMonths(2)->format('d/m/Y');
    $update = picUpdate($f['owner'], picCsv('071111111-26;MATEO;QUINTERO;PESTANO;'.$baby.';M;HI'));
    $item = Item::query()->where('corporate_population_update_id', $update->id)->where('file_data', 'like', '%MATEO%')->firstOrFail();

    expect($item->action)->toBe(Item::ACTION_REVIEW)
        ->and(PopulationItemExplainer::explain($item, null, null, CorporateAffiliatePlanSynchronizer::planRowsFor($f['owner']), 5)['conclusion'])->toContain('Ninguno incluye esa edad');

    $f['row']->forceFill(['age_range_id' => 1])->save();

    $evaluated = PopulationItemCorrection::reevaluateOnly($item);

    expect($evaluated->action)->toBe(Item::ACTION_ADD)
        ->and($evaluated->selected)->toBeTrue()
        ->and((int) $evaluated->details['plan_row_id'])->toBe((int) $f['row']->id);
});

it('no permite correcciones si la carga ya no está lista para revisar', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $update->forceFill(['status' => CorporatePopulationUpdate::STATUS_APPLYING])->save();

    expect(fn () => PopulationItemCorrection::markReviewed(picItemFor($update, $f['luis']), 'Revisión tardía de la fila.', picActor()))
        ->toThrow(RuntimeException::class, 'ya no se puede modificar');
});

it('la pantalla muestra la explicación, la pestaña de diferencias y las acciones', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $user = User::factory()->make(['id' => 987911, 'name' => 'Usuario Corrección Pest', 'email' => 'correccion-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::class, ['record' => $f['owner']->id, 'update' => $update->public_id])
        ->assertOk()
        ->assertSee('Coincide con diferencias')
        ->call('setTab', Item::ACTION_REVIEW)
        ->assertSee('Posiblemente es «ROSA AMELIA PESTANA ZURITA»');

    $item = picItemFor($update, $f['rosa']);
    $component->mountTableAction('explain', $item);
    expect($component->get('mountedActions')[0]['name'] ?? null)->toBe('explain');

    $html = view('filament.business.resources.affiliation-corporates.partials.population-item-explanation', [
        'explanation' => PopulationItemExplainer::explain($item, $f['rosa']->fresh(), null, CorporateAffiliatePlanSynchronizer::planRowsFor($f['owner']), 5),
    ])->render();

    expect($html)->toContain('Qué buscó el sistema', 'Cómo comparó', 'Resultado dato por dato', 'Conclusión', 'Qué hacer', 'No es la misma persona');
});

it('la ventana «Corregir datos» viene prellenada con el archivo, verifica y guarda', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['luis']);
    $user = User::factory()->make(['id' => 987912, 'name' => 'Usuario Corrección Pest', 'email' => 'correccion-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::class, ['record' => $f['owner']->id, 'update' => $update->public_id])
        ->call('setTab', App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::TAB_MATCH_DIFFERENCES)
        ->assertSee('LUIS ALBERTO QUINTERO PESTANO')
        ->mountTableAction('correct', $item);

    expect($component->get('mountedActions.0.data.first_name'))->toBe('LUIS ALBERTO')
        ->and($component->get('mountedActions.0.data.last_name'))->toBe('QUINTERO PESTANO')
        ->and($component->get('mountedActions.0.data.expected'))->toBe(PopulationItemCorrection::fingerprint($f['luis']->fresh()));

    $component
        ->set('mountedActions.0.data.note', 'Nombres y apellidos estaban cruzados en la ficha.')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertNotified('Ficha corregida');

    expect($f['luis']->fresh()->first_name)->toBe('LUIS ALBERTO')
        ->and($f['luis']->fresh()->last_name)->toBe('QUINTERO PESTANO')
        ->and(picItemFor($update, $f['luis'])->details['differences'])->toBe([]);
});

it('elimina una carga lista para revisar con su análisis, claves y archivo, y deja traza con la razón', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    $item = picItemFor($update, $f['luis']);
    PopulationItemCorrection::correct($item, picFileValues($item), 'Nombres y apellidos cruzados en la ficha.', picActor(), PopulationItemCorrection::fingerprint($f['luis']->fresh()));

    $challenge = new App\Models\CorporatePopulationOtpChallenge;
    $challenge->forceFill(['public_id' => (string) Str::uuid(), 'user_id' => 987910, 'corporate_population_update_id' => $update->id, 'purpose' => 'APPLY', 'code_hash' => 'x', 'expires_at' => now()->addMinutes(5)])->save();

    expect(App\Support\AffiliationCorporates\Population\PopulationUpdateDeletion::savedCorrections($update))->toMatchArray(['corrections' => 1, 'affiliates' => [(int) $f['luis']->id]]);

    App\Support\AffiliationCorporates\Population\PopulationUpdateDeletion::delete($update, picActor(), 'La empresa envió un archivo equivocado; se subirá otro.');

    expect(CorporatePopulationUpdate::query()->find($update->id))->toBeNull()
        ->and(Item::query()->where('corporate_population_update_id', $update->id)->count())->toBe(0)
        ->and(App\Models\CorporatePopulationOtpChallenge::query()->where('corporate_population_update_id', $update->id)->count())->toBe(0)
        ->and(Storage::disk('local')->exists($update->file_path))->toBeFalse()
        ->and($f['luis']->fresh()->first_name)->toBe('LUIS ALBERTO')
        ->and($f['luis']->fresh()->status)->toBe('ACTIVO')
        ->and((int) $f['owner']->fresh()->poblation)->toBe(5)
        ->and(DB::table('logs')->where('action', 'AUDIT_CORPORATE_POPULATION_DELETED')->where('response', 'like', '%archivo equivocado%')->where('response', 'like', '%"update_id":'.$update->id.'%')->exists())->toBeTrue();
});

it('no elimina cargas aplicadas, sin razón suficiente ni con una sincronización en curso', function (): void {
    $deletion = App\Support\AffiliationCorporates\Population\PopulationUpdateDeletion::class;
    $f = picFixture();
    $update = picUpdate($f['owner']);

    expect(fn () => $deletion::delete($update, picActor(), 'corta'))->toThrow(RuntimeException::class, 'mínimo');

    Cache::put(SyncCorporatePopulationItemsJob::busyKey($update->id), 1, 60);
    expect($deletion::blockingReason($update))->toContain('sincronización')
        ->and(fn () => $deletion::delete($update, picActor(), 'Razón suficientemente larga para eliminar.'))->toThrow(RuntimeException::class, 'sincronización');
    Cache::forget(SyncCorporatePopulationItemsJob::busyKey($update->id));

    $update->forceFill(['status' => CorporatePopulationUpdate::STATUS_APPLIED])->save();
    expect(fn () => $deletion::delete($update->fresh(), picActor(), 'Razón suficientemente larga para eliminar.'))->toThrow(RuntimeException::class, 'Lista para revisar')
        ->and(CorporatePopulationUpdate::query()->find($update->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($update->file_path))->toBeTrue();
});

it('la tabla de cargas ofrece «Eliminar» solo en las listas para revisar y exige la razón', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = picFixture();
    $ready = picUpdate($f['owner']);
    $applied = picUpdate($f['owner']);
    $applied->forceFill(['status' => CorporatePopulationUpdate::STATUS_APPLIED])->save();
    $user = User::factory()->make(['id' => 987913, 'name' => 'Usuario Corrección Pest', 'email' => 'correccion-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdates::class, ['record' => $f['owner']->id])
        ->assertOk()
        ->assertTableActionVisible('delete', $ready)
        ->assertTableActionHidden('delete', $applied)
        ->mountTableAction('delete', $ready)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.reason'])
        ->set('mountedActions.0.data.reason', 'Se cargó el archivo de otra fecha por error.')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertNotified('Carga eliminada');

    expect(CorporatePopulationUpdate::query()->find($ready->id))->toBeNull()
        ->and(CorporatePopulationUpdate::query()->find($applied->id))->not->toBeNull();
});

/**
 * Usuario de Negocios con (o sin) el permiso de usar su propia clave para aplicar población.
 */
function picOtpUser(bool $selfPermission, int $id = 987930): User
{
    $user = User::factory()->make(['id' => $id, 'name' => 'Analista Clave Propia Pest', 'email' => 'clave-propia-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['NEGOCIOS']]);
    $permissions = [
        new App\Models\Permission(['slug' => 'afiliaciones-corporativas', 'module' => 'NEGOCIOS']),
        new App\Models\Permission(['slug' => App\Support\Filament\BusinessFilamentActionPermissionRegistry::UPDATE_CORPORATE_POPULATION, 'module' => 'NEGOCIOS']),
    ];
    if ($selfPermission) {
        $permissions[] = new App\Models\Permission(['slug' => App\Support\Filament\BusinessFilamentActionPermissionRegistry::SELF_OTP_POPULATION_APPLY, 'module' => 'NEGOCIOS']);
    }

    return $user->setRelation('permissions', new Illuminate\Database\Eloquent\Collection($permissions));
}

function picOtpRecipients(bool $active = true): void
{
    App\Models\SystemNotificationRecipientSetting::for(App\Enums\SystemNotificationKey::CorporatePopulationUpdate)
        ->forceFill(['notification_emails' => ['autorizador-pest@tudrencasa.com'], 'notification_phones' => [], 'is_active' => $active])->save();
}

it('usa la clave propia solo con permiso, con motivo, deja traza y avisa al autorizador', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    picOtpRecipients();

    $kind = App\Enums\SystemOtpCodeKind::PopulationApply;
    $without = picOtpUser(false, 987931);
    $with = picOtpUser(true);

    expect(App\Support\Otp\SelfAuthorizedOtp::userCan($without, $kind))->toBeFalse()
        ->and(App\Support\Otp\SelfAuthorizedOtp::userCan($with, $kind))->toBeTrue()
        ->and(App\Support\Otp\SelfAuthorizedOtp::userCan($with, App\Enums\SystemOtpCodeKind::PopulationRollback))->toBeFalse();

    $issued = App\Support\AffiliationCorporates\Population\CorporatePopulationOtp::issue($with, $update, App\Models\CorporatePopulationOtpChallenge::PURPOSE_APPLY);
    $challenge = $issued['challenge'];
    $sent = null;
    Mail::assertSent(App\Mail\CorporatePopulationOtpMail::class, function ($mail) use (&$sent): bool {
        $sent = $mail->emailPayload['otp_code'];

        return true;
    });

    expect(App\Support\Otp\SelfAuthorizedOtp::pendingCode($challenge, $with, $kind))->toBe($sent)
        ->and(App\Support\Otp\SelfAuthorizedOtp::pendingCode($challenge, $without, $kind))->toBeNull()
        ->and(fn () => App\Support\Otp\SelfAuthorizedOtp::resolveCode($challenge, $without, $kind, true, 'El autorizador no respondió hoy.', null))->toThrow(RuntimeException::class, 'No tiene permiso')
        ->and(fn () => App\Support\Otp\SelfAuthorizedOtp::resolveCode($challenge, $with, $kind, true, 'corto', null))->toThrow(RuntimeException::class, 'mínimo');

    $code = App\Support\Otp\SelfAuthorizedOtp::resolveCode($challenge, $with, $kind, true, 'El autorizador no respondió hoy.', null);
    expect(App\Support\AffiliationCorporates\Population\CorporatePopulationOtp::verifyAndConsume($challenge, $code, $with, $update, App\Models\CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toBeTrue();

    App\Support\Otp\SelfAuthorizedOtp::recordUse($kind, $challenge, $with, 'El autorizador no respondió hoy.', 'Carga pest', ['autorizador-pest@tudrencasa.com'], []);

    $record = App\Models\SystemOtpCode::query()->where('challenge_id', $challenge->id)->where('challenge_type', $challenge->getMorphClass())->latest('id')->firstOrFail();
    expect($record->details['Autoautorizada por'])->toBe('Analista Clave Propia Pest')
        ->and($record->used_at)->not->toBeNull()
        ->and(App\Support\Otp\SelfAuthorizedOtp::pendingCode($challenge->fresh(), $with, $kind))->toBeNull()
        ->and(DB::table('logs')->where('action', 'AUDIT_OTP_SELF_AUTHORIZED')->where('response', 'like', '%El autorizador no respondi%')->exists())->toBeTrue();

    Queue::assertPushed(App\Jobs\SendOtpSelfAuthorizationNoticeJob::class, fn ($job): bool => $job->emails === ['autorizador-pest@tudrencasa.com'] && $job->userName === 'Analista Clave Propia Pest');

    (new App\Jobs\SendOtpSelfAuthorizationNoticeJob('POPULATION_APPLY', 'Analista', 'Carga pest', 'Motivo de prueba largo.', now()->toIso8601String(), ['autorizador-pest@tudrencasa.com'], []))->handle();
    Mail::assertSent(App\Mail\OtpSelfAuthorizationNoticeMail::class, fn ($mail): bool => $mail->hasTo('autorizador-pest@tudrencasa.com'));
});

it('si la clave no se puede entregar, sin permiso se cancela y con permiso queda viva para usarla', function (): void {
    $f = picFixture();
    $update = picUpdate($f['owner']);
    picOtpRecipients(false);

    expect(fn () => App\Support\AffiliationCorporates\Population\CorporatePopulationOtp::issue(picOtpUser(false, 987932), $update, App\Models\CorporatePopulationOtpChallenge::PURPOSE_APPLY))
        ->toThrow(RuntimeException::class, 'pausada');

    $with = picOtpUser(true);
    $issued = App\Support\AffiliationCorporates\Population\CorporatePopulationOtp::issue($with, $update, App\Models\CorporatePopulationOtpChallenge::PURPOSE_APPLY);

    expect($issued['delivered'])->toBeFalse()
        ->and($issued['challenge']->isActive())->toBeTrue()
        ->and(App\Support\Otp\SelfAuthorizedOtp::pendingCode($issued['challenge'], $with, App\Enums\SystemOtpCodeKind::PopulationApply))->toMatch('/^\d{6}$/');
});

it('en la ventana «Aprobar y aplicar» la casilla de clave propia aplica sin escribir la clave', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = picFixture();
    $update = picUpdate($f['owner']);
    picOtpRecipients();
    PopulationItemCorrection::resolveDuplicate(picItemFor($update, $f['copy']), [], 'Misma persona cargada dos veces en la base.', picActor());

    $component = Livewire\Livewire::actingAs(picOtpUser(true))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::class, ['record' => $f['owner']->id, 'update' => $update->public_id])
        ->call('mountAction', 'approve')
        ->call('callMountedAction')
        ->assertNotified('Clave enviada');

    $component
        ->set('mountedActions.0.data.use_own_code', true)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.own_code_reason'])
        ->set('mountedActions.0.data.own_code_reason', 'El autorizador no respondió y la empresa espera la carga.')
        ->call('callMountedAction')
        ->assertNotified('Aplicando la actualización');

    expect($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_APPLYING);
    Queue::assertPushed(App\Jobs\SendOtpSelfAuthorizationNoticeJob::class);
});

it('sin el permiso la ventana no ofrece la casilla y exige la clave', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = picFixture();
    $update = picUpdate($f['owner']);
    picOtpRecipients();
    PopulationItemCorrection::resolveDuplicate(picItemFor($update, $f['copy']), [], 'Misma persona cargada dos veces en la base.', picActor());

    Livewire\Livewire::actingAs(picOtpUser(false, 987933))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::class, ['record' => $f['owner']->id, 'update' => $update->public_id])
        ->call('mountAction', 'approve')
        ->call('callMountedAction')
        ->assertDontSee('Usar la clave que yo solicité')
        ->set('mountedActions.0.data.use_own_code', true)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.otp_code']);

    expect($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED);
});
