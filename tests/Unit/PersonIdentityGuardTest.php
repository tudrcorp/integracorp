<?php

declare(strict_types=1);

use App\Enums\PersonIdentityFindingKind as Kind;
use App\Enums\PersonIdentitySource;
use App\Filament\Business\Resources\PersonIdentityFindings\Pages\ViewPersonIdentityFinding;
use App\Livewire\CompanyAssociateRegistration;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CompanyAssociate;
use App\Models\PersonIdentityFinding as Finding;
use App\Models\User;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonLivewireNotifier;
use App\Support\Affiliations\PersonIdentity\PersonIdentityGuard;
use App\Support\Affiliations\PersonIdentity\PersonIdentityMatcher;
use App\Support\Affiliations\PersonIdentity\PersonIdentityNormalizer;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use App\Support\Affiliations\PersonIdentity\PersonIdentityResolver;
use App\Support\Affiliations\PersonIdentity\PersonIdentityScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Candado y detector de personas duplicadas. Escribe: corre dentro de una transacción que siempre
 * se revierte, sobre afiliaciones de prueba (TEST-PID-…) clonadas de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->corporateTemplate = AffiliationCorporate::query()->first();
    $this->individualTemplate = Affiliation::query()->first();
    $this->associateTemplate = CompanyAssociate::query()->first();

    if (! $this->corporateTemplate || ! $this->individualTemplate || ! $this->associateTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->actor = User::factory()->make(['id' => 999999, 'name' => 'Analista de prueba', 'departament' => ['SUPERADMIN']]);
});

afterEach(function (): void {
    DB::rollBack();
});

function pidCorporate(object $test, string $status = 'ACTIVA'): AffiliationCorporate
{
    $affiliation = $test->corporateTemplate->replicate();
    $affiliation->code = 'TEST-PID-C-'.Str::upper(Str::random(6));
    $affiliation->name_corporate = 'EMPRESA DE PRUEBA PID';
    $affiliation->status = $status;
    $affiliation->save();

    return $affiliation;
}

function pidIndividual(object $test): Affiliation
{
    $affiliation = $test->individualTemplate->replicate();
    $affiliation->code = 'TEST-PID-I-'.Str::upper(Str::random(6));
    $affiliation->status = 'ACTIVA';
    $affiliation->save();

    return $affiliation;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function pidCorporateAffiliate(AffiliationCorporate $owner, array $attributes): AffiliateCorporate
{
    $affiliate = new AffiliateCorporate;
    $affiliate->forceFill([
        'affiliation_corporate_id' => $owner->getKey(),
        'status' => 'ACTIVO',
        ...$attributes,
    ])->save();

    return $affiliate;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function pidAssociate(object $test, array $attributes): CompanyAssociate
{
    $associate = $test->associateTemplate->replicate();
    $associate->forceFill([
        'status' => 'ACTIVO',
        'date_init' => null,
        'date_end' => null,
        'vaucher_ils' => null,
        'registration_period_days' => 1,
        ...$attributes,
    ])->save();

    return $associate;
}

function pidUniqueDocument(): string
{
    return (string) random_int(70000000, 79999999);
}

it('reconoce la misma cédula escrita de varias formas y descarta las de relleno', function (): void {
    expect(PersonIdentityNormalizer::documentKey('M 19131154 2'))->toBe('191311542')
        ->and(PersonIdentityNormalizer::documentKey('M-19131154-2'))->toBe('191311542')
        ->and(PersonIdentityNormalizer::documentKey('19131154-2'))->toBe('191311542')
        ->and(PersonIdentityNormalizer::documentKey('191311542'))->toBe('191311542')
        ->and(PersonIdentityNormalizer::documentKey('V-22.222.937'))->toBe('22222937')
        ->and(PersonIdentityNormalizer::documentKey('Tb287966'))->toBe('TB287966')
        ->and(PersonIdentityNormalizer::documentKey('00000001'))->toBeNull()
        ->and(PersonIdentityNormalizer::documentKey('11111111'))->toBeNull()
        ->and(PersonIdentityNormalizer::documentKey('S/C'))->toBeNull()
        ->and(PersonIdentityNormalizer::documentKey(''))->toBeNull();
});

it('reconoce el mismo nombre en otro orden, repetido o con tildes', function (): void {
    $repeated = PersonIdentityNormalizer::nameKey('ROBLES OTERO ESTHEFANO JOSUE', 'ROBLES OTERO ESTHEFANO JOSUE');

    expect($repeated)->toBe(PersonIdentityNormalizer::nameKey('ESTHEFANO JOSUE ROBLES OTERO'))
        ->and(PersonIdentityNormalizer::nameKey('José Peña de la Rosa'))->toBe(PersonIdentityNormalizer::nameKey('JOSE PENA ROSA'))
        ->and(PersonIdentityNormalizer::nameKey('Almazol'))->toBeNull()
        ->and(PersonIdentityNormalizer::nameRelation(
            PersonIdentityNormalizer::nameKey('Yuraima Vargas'),
            PersonIdentityNormalizer::nameKey('YURAIMA DEL CARMEN VARGAS PEREZ'),
        ))->toBe('subset');
});

it('detecta el nombre cargado dos veces', function (): void {
    expect(PersonIdentityNormalizer::isRepeatedName('ROBLES OTERO ESTHEFANO JOSUE', 'ROBLES OTERO ESTHEFANO JOSUE'))->toBeTrue()
        ->and(PersonIdentityNormalizer::isRepeatedName('ROBLES OTERO ESTHEFANO ROBLES OTERO ESTHEFANO'))->toBeTrue()
        ->and(PersonIdentityNormalizer::isRepeatedName('ESTHEFANO JOSUE', 'ROBLES OTERO'))->toBeFalse()
        ->and(PersonIdentityNormalizer::isRepeatedName('MARIA MARIA'))->toBeFalse()
        ->and(PersonIdentityNormalizer::isRepeatedName('GOMEZ BELLORIN NIA VALERIA', '...'))->toBeFalse();
});

it('distingue a la misma persona de hermanos y representantes con la misma cédula', function (): void {
    $owner = pidCorporate($this);
    $record = fn (string $first, string $last, string $document, string $birth): PersonIdentityRecord => PersonIdentityRecord::fromModel(
        (new AffiliateCorporate)->forceFill(['first_name' => $first, 'last_name' => $last, 'nro_identificacion' => $document, 'birth_date' => $birth, 'status' => 'ACTIVO', 'affiliation_corporate_id' => $owner->getKey()]),
        $owner,
    );

    $repeated = $record('ROBLES OTERO ESTHEFANO JOSUE', 'ROBLES OTERO ESTHEFANO JOSUE', '191311542', '4/12/2019');
    $clean = $record('ESTHEFANO JOSUE', 'ROBLES OTERO', 'M 19131154 2', '04/12/2019');
    $brother = $record('ALAN JOSUE', 'ROBLES OTERO', '191311542', '25/01/2022');
    $father = $record('ISMAEL RAFAEL', 'GONZALEZ HERNANDEZ', '19314260', '20/02/1990');
    $son = $record('CESAR ARMANDO', 'GONZALEZ FERNANDEZ', '19314260', '11/05/2017');
    $mother = $record('MARIA', 'PEREZ', '12345678', '01/01/1980');
    $daughter = $record('MARIA', 'PEREZ GOMEZ', '12345678', '01/01/2015');
    $schoolId = $record('THIAGO MATHIAS', 'SANTELLA GUZMAN', 'V11618958400', '16/10/2016');
    $minorId = $record('THIAGO MATHIAS', 'SANTELLA GUZMAN', 'M-18958400-1', '16/10/2016');

    expect(PersonIdentityMatcher::match($repeated, $clean))->toBe(Kind::SameDocument)
        ->and(PersonIdentityMatcher::match($repeated, $brother))->toBeNull()
        ->and(PersonIdentityMatcher::match($father, $son))->toBeNull()
        ->and(PersonIdentityMatcher::match($mother, $daughter))->toBeNull()
        ->and(PersonIdentityMatcher::match($schoolId, $minorId))->toBe(Kind::SameNameAndBirthDate);
});

it('bloquea registrar en otra afiliación a una persona vigente y explica dónde está', function (): void {
    $first = pidCorporate($this);
    $second = pidCorporate($this);
    $document = pidUniqueDocument();

    pidCorporateAffiliate($first, ['first_name' => 'TOMAS ANDRES', 'last_name' => 'QUINTERO VALE', 'nro_identificacion' => $document.'2', 'birth_date' => '4/12/2019']);

    try {
        pidCorporateAffiliate($second, ['first_name' => 'QUINTERO VALE', 'last_name' => 'TOMAS ANDRES', 'nro_identificacion' => 'M '.$document.' 2', 'birth_date' => '04/12/2019']);
        $this->fail('Debió bloquear el duplicado.');
    } catch (DuplicatePersonIdentityException $exception) {
        expect($exception->getMessage())
            ->toContain($first->code)
            ->toContain('EMPRESA DE PRUEBA PID')
            ->toContain('misma cédula')
            ->toContain('Personas duplicadas')
            ->and($exception->publicMessage())->not->toContain($first->code);
    }

    expect(AffiliateCorporate::query()->where('affiliation_corporate_id', $second->getKey())->count())->toBe(0);
});

it('deja registrar hermanos con la cédula del representante y a quien ya fue dado de baja', function (): void {
    $first = pidCorporate($this);
    $second = pidCorporate($this);
    $document = pidUniqueDocument();

    $old = pidCorporateAffiliate($first, ['first_name' => 'ANA MARIA', 'last_name' => 'LOPEZ DIAZ', 'nro_identificacion' => $document, 'birth_date' => '01/01/2015']);
    pidCorporateAffiliate($first, ['first_name' => 'LUIS JOSE', 'last_name' => 'LOPEZ DIAZ', 'nro_identificacion' => $document, 'birth_date' => '01/01/2017']);

    $old->forceFill(['status' => 'INACTIVO'])->save();
    $new = pidCorporateAffiliate($second, ['first_name' => 'ANA MARIA', 'last_name' => 'LOPEZ DIAZ', 'nro_identificacion' => $document, 'birth_date' => '01/01/2015']);

    expect($new->exists)->toBeTrue()
        ->and($new->identity_document_key)->toBe($document);

    expect(fn () => $old->forceFill(['status' => 'ACTIVO'])->save())->toThrow(DuplicatePersonIdentityException::class);
});

it('no cuenta a las personas de una afiliación dada de baja', function (): void {
    $excluded = pidCorporate($this, 'EXCLUIDO');
    $active = pidCorporate($this);
    $document = pidUniqueDocument();

    pidCorporateAffiliate($excluded, ['first_name' => 'PEDRO', 'last_name' => 'RAMOS SOTO', 'nro_identificacion' => $document, 'birth_date' => '10/10/1990']);
    $again = pidCorporateAffiliate($active, ['first_name' => 'PEDRO', 'last_name' => 'RAMOS SOTO', 'nro_identificacion' => $document, 'birth_date' => '10/10/1990']);

    expect($again->exists)->toBeTrue();
});

it('bloquea a la misma persona entre una afiliación individual y una corporativa', function (): void {
    $individual = pidIndividual($this);
    $corporate = pidCorporate($this);
    $document = pidUniqueDocument();

    $member = Affiliate::query()->firstOrFail()->replicate();
    $member->forceFill([
        'affiliation_id' => $individual->getKey(),
        'full_name' => 'CARLA ANDREINA MORA PAEZ',
        'nro_identificacion' => $document,
        'birth_date' => '05/05/1995',
        'status' => 'ACTIVO',
    ])->save();

    expect(fn () => pidCorporateAffiliate($corporate, ['first_name' => 'CARLA ANDREINA', 'last_name' => 'MORA PAEZ', 'nro_identificacion' => 'V-'.$document, 'birth_date' => '1995-05-05']))
        ->toThrow(DuplicatePersonIdentityException::class, $individual->code);
});

it('bloquea guardar un nombre cargado dos veces', function (): void {
    $owner = pidCorporate($this);

    expect(fn () => pidCorporateAffiliate($owner, ['first_name' => 'QUINTERO VALE LUCAS', 'last_name' => 'QUINTERO VALE LUCAS', 'nro_identificacion' => pidUniqueDocument(), 'birth_date' => '25/01/2022']))
        ->toThrow(DuplicatePersonIdentityException::class, 'cargado dos veces');
});

it('no bloquea editar otros datos de un registro viejo con el nombre repetido', function (): void {
    $owner = pidCorporate($this);
    $id = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $owner->getKey(),
        'first_name' => 'QUINTERO VALE LUCAS',
        'last_name' => 'QUINTERO VALE LUCAS',
        'nro_identificacion' => pidUniqueDocument(),
        'birth_date' => '25/01/2022',
        'status' => 'ACTIVO',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacy = AffiliateCorporate::query()->findOrFail($id);
    $legacy->forceFill(['phone' => '04240000000'])->save();

    expect($legacy->fresh()->phone)->toBe('04240000000');
});

it('completa los datos de identidad aunque el modelo se cargue con pocas columnas', function (): void {
    $owner = pidCorporate($this);
    $affiliate = pidCorporateAffiliate($owner, ['first_name' => 'JUAN PABLO', 'last_name' => 'RIOS LEON', 'nro_identificacion' => pidUniqueDocument(), 'birth_date' => '02/02/1992']);
    $key = $affiliate->identity_document_key;

    $partial = AffiliateCorporate::query()->select(['id', 'status'])->findOrFail($affiliate->getKey());
    $partial->forceFill(['status' => 'PRE-APROBADA'])->save();

    expect(AffiliateCorporate::query()->findOrFail($affiliate->getKey())->identity_document_key)->toBe($key);
});

it('deja dos vuelos de Nuevos Negocios en días distintos y bloquea el mismo día', function (): void {
    $document = pidUniqueDocument();
    $base = ['full_name' => 'YURAIMA VARGAS', 'identity_card' => $document, 'birth_date' => '1972-05-11'];

    pidAssociate($this, [...$base, 'flight_date' => now()->addDays(3)->toDateString()]);
    $otherDay = pidAssociate($this, [...$base, 'flight_date' => now()->addDays(5)->toDateString()]);

    expect($otherDay->exists)->toBeTrue();

    expect(fn () => pidAssociate($this, [...$base, 'flight_date' => now()->addDays(3)->toDateString()]))
        ->toThrow(DuplicatePersonIdentityException::class);
});

it('bloquea un vuelo de Nuevos Negocios de quien ya está vigente en una corporativa, y no uno vencido', function (): void {
    $owner = pidCorporate($this);
    $document = pidUniqueDocument();

    pidAssociate($this, ['full_name' => 'DARIO ALDANA', 'identity_card' => $document, 'birth_date' => '2002-09-24', 'flight_date' => now()->subDays(10)->toDateString()]);
    pidCorporateAffiliate($owner, ['first_name' => 'DARIO', 'last_name' => 'ALDANA', 'nro_identificacion' => $document, 'birth_date' => '24/09/2002']);

    expect(fn () => pidAssociate($this, ['full_name' => 'Darío Aldana', 'identity_card' => $document, 'birth_date' => '2002-09-24', 'flight_date' => now()->addDay()->toDateString()]))
        ->toThrow(DuplicatePersonIdentityException::class, $owner->code);
});

it('revisa las filas del lote corporativo entre sí y contra la base', function (): void {
    $owner = pidCorporate($this);
    $document = pidUniqueDocument();
    $row = ['first_name' => 'SOFIA', 'last_name' => 'MENDEZ LARA', 'nro_identificacion' => $document, 'birth_date' => '03/03/2010', 'status' => 'PRE-APROBADA'];

    $prepared = PersonIdentityGuard::assertCorporateRowsAreUnique([$row], $owner);

    expect($prepared[0]['identity_document_key'])->toBe($document)
        ->and($prepared[0]['identity_name_key'])->toBe('LARA MENDEZ SOFIA');

    expect(fn () => PersonIdentityGuard::assertCorporateRowsAreUnique([$row, [...$row, 'nro_identificacion' => 'M-'.$document]], $owner))
        ->toThrow(DuplicatePersonIdentityException::class);
});

it('detecta el caso, exige nota, lo marca como no duplicado y deja de bloquear entre esos dos', function (): void {
    $first = pidCorporate($this);
    $second = pidCorporate($this);
    $document = pidUniqueDocument();
    $insert = fn (AffiliationCorporate $owner, string $first, string $last): int => DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $owner->getKey(),
        'first_name' => $first,
        'last_name' => $last,
        'nro_identificacion' => $document,
        'birth_date' => '07/07/2007',
        'status' => 'ACTIVO',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $idA = $insert($first, 'GABRIEL', 'SOSA PINTO');
    $idB = $insert($second, 'SOSA PINTO', 'GABRIEL');

    $records = [
        PersonIdentityRecord::fromModel(AffiliateCorporate::query()->findOrFail($idA)),
        PersonIdentityRecord::fromModel(AffiliateCorporate::query()->findOrFail($idB)),
    ];
    $detected = PersonIdentityScanner::detect($records);
    PersonIdentityScanner::sync($detected, $this->actor, array_keys($detected));

    $finding = Finding::query()->where('pair_key', array_key_first($detected))->firstOrFail();

    expect($finding->status)->toBe(Finding::STATUS_OPEN)
        ->and($finding->kind)->toBe(Kind::SameDocument->value)
        ->and($finding->record_a_type)->toBe(PersonIdentitySource::Corporate->value);

    expect(fn () => PersonIdentityResolver::dismiss($finding, 'corto', $this->actor))->toThrow(RuntimeException::class, 'mínimo');

    PersonIdentityResolver::dismiss($finding, 'Son primos homónimos según la empresa, confirmado por correo.', $this->actor);

    expect($finding->fresh()->status)->toBe(Finding::STATUS_DISMISSED);

    $b = AffiliateCorporate::query()->findOrFail($idB);
    $b->forceFill(['status' => 'PRE-APROBADA'])->save();

    expect($b->fresh()->status)->toBe('PRE-APROBADA');
});

it('corrige desde el detector con nota, revierte la corrección y cierra el caso del nombre repetido', function (): void {
    $owner = pidCorporate($this);
    $id = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $owner->getKey(),
        'first_name' => 'QUINTERO VALE TOMAS ANDRES',
        'last_name' => 'QUINTERO VALE TOMAS ANDRES',
        'nro_identificacion' => pidUniqueDocument(),
        'birth_date' => '4/12/2019',
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $record = PersonIdentityRecord::fromModel(AffiliateCorporate::query()->findOrFail($id));
    $detected = PersonIdentityScanner::detect([$record]);
    PersonIdentityScanner::sync($detected, $this->actor, array_keys($detected));
    $finding = Finding::query()->where('pair_key', Finding::pairKey(Kind::RepeatedName, $record))->firstOrFail();

    expect(fn () => PersonIdentityResolver::correct($finding, 'a', ['first_name' => 'TOMAS ANDRES', 'last_name' => 'QUINTERO VALE'], '', $this->actor))
        ->toThrow(RuntimeException::class);

    PersonIdentityResolver::correct($finding, 'a', ['first_name' => 'TOMAS ANDRES', 'last_name' => 'QUINTERO VALE'], 'Se corrige según la cédula enviada por la empresa.', $this->actor);

    $affiliate = AffiliateCorporate::query()->findOrFail($id);

    expect($affiliate->first_name)->toBe('TOMAS ANDRES')
        ->and($affiliate->last_name)->toBe('QUINTERO VALE')
        ->and($finding->fresh()->status)->toBe(Finding::STATUS_CORRECTED)
        ->and(PersonIdentityResolver::revertibleCorrection($finding->fresh()))->not->toBeNull();

    PersonIdentityResolver::revertCorrection($finding->fresh(), 'El analista se equivocó de registro, se deshace.', $this->actor);

    expect(AffiliateCorporate::query()->findOrFail($id)->last_name)->toBe('QUINTERO VALE TOMAS ANDRES')
        ->and($finding->fresh()->status)->toBe(Finding::STATUS_OPEN);
});

it('da de baja el duplicado sin plan descontando solo la población', function (): void {
    $first = pidCorporate($this);
    $second = pidCorporate($this);
    $second->forceFill(['poblation' => 10])->save();
    $document = pidUniqueDocument();

    pidCorporateAffiliate($first, ['first_name' => 'MATEO', 'last_name' => 'VERA CRUZ', 'nro_identificacion' => $document, 'birth_date' => '09/09/2012', 'plan_id' => 5, 'fee' => 100]);
    $duplicateId = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $second->getKey(),
        'first_name' => 'VERA CRUZ MATEO',
        'last_name' => 'VERA CRUZ MATEO',
        'nro_identificacion' => 'M-'.$document,
        'birth_date' => '9/9/2012',
        'plan_id' => 0,
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $records = array_map(
        fn (int $id): PersonIdentityRecord => PersonIdentityRecord::fromModel(AffiliateCorporate::query()->findOrFail($id)),
        AffiliateCorporate::query()->whereIn('affiliation_corporate_id', [$first->getKey(), $second->getKey()])->pluck('id')->all(),
    );
    $detected = array_filter(PersonIdentityScanner::detect($records), fn (array $found): bool => $found['kind'] === Kind::SameDocument);
    PersonIdentityScanner::sync($detected, $this->actor, array_keys($detected));
    $finding = Finding::query()->where('pair_key', array_key_first($detected))->firstOrFail();
    $side = (int) $finding->record_a_id === $duplicateId ? 'a' : 'b';

    PersonIdentityResolver::deactivate($finding, $side, 'Se cargó dos veces en el padrón; el bueno es el de la otra afiliación.', $this->actor);

    expect(AffiliateCorporate::query()->findOrFail($duplicateId)->status)->toBe('INACTIVO')
        ->and((int) $second->fresh()->poblation)->toBe(9)
        ->and($finding->fresh()->status)->toBe(Finding::STATUS_CORRECTED);
});

it('el análisis de población manda a revisar a quien ya está vigente en otra afiliación, salvo si el archivo lo da de baja', function (): void {
    $other = pidCorporate($this);
    $owner = pidCorporate($this);
    $document = pidUniqueDocument();
    $person = ['first_name' => 'ELENA SOFIA', 'last_name' => 'BRICEÑO MATA', 'document' => $document, 'birth_date' => '1999-03-15'];

    pidCorporateAffiliate($other, ['first_name' => 'ELENA SOFIA', 'last_name' => 'BRICEÑO MATA', 'nro_identificacion' => $document, 'birth_date' => '15/03/1999']);
    $leaving = pidCorporateAffiliate($owner, ['first_name' => 'RAUL', 'last_name' => 'MATA SALAS', 'nro_identificacion' => pidUniqueDocument(), 'birth_date' => '01/01/1970']);

    $method = new ReflectionMethod(App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer::class, 'blockPeopleActiveElsewhere');
    $items = $method->invoke(null, $owner, [
        ['action' => 'ADD', 'affiliate_corporate_id' => null, 'file_data' => $person, 'selectable' => true, 'selected' => true, 'reason' => null, 'details' => []],
        ['action' => 'ADD', 'affiliate_corporate_id' => null, 'file_data' => ['first_name' => 'RAUL', 'last_name' => 'MATA SALAS', 'document' => (string) $leaving->nro_identificacion, 'birth_date' => '1970-01-01'], 'selectable' => true, 'selected' => true, 'reason' => null, 'details' => []],
        ['action' => 'DEACTIVATE', 'affiliate_corporate_id' => $leaving->getKey(), 'file_data' => null, 'selectable' => true, 'selected' => true, 'reason' => null, 'details' => []],
    ]);

    expect($items[0]['action'])->toBe('REVIEW')
        ->and($items[0]['selectable'])->toBeFalse()
        ->and($items[0]['reason'])->toContain($other->code)
        ->and($items[1]['action'])->toBe('ADD');
});

it('muestra el bloqueo como notificación solo en los paneles internos', function (): void {
    $exception = DuplicatePersonIdentityException::repeatedName(PersonIdentityRecord::fromModel((new AffiliateCorporate)->forceFill(['first_name' => 'A B', 'last_name' => 'A B'])));
    $panelComponent = new ViewPersonIdentityFinding;
    $publicComponent = new CompanyAssociateRegistration;

    expect(DuplicatePersonLivewireNotifier::handles($panelComponent, $exception))->toBeTrue()
        ->and(DuplicatePersonLivewireNotifier::handles($publicComponent, $exception))->toBeFalse()
        ->and(DuplicatePersonLivewireNotifier::handles($panelComponent, new RuntimeException('otro')))->toBeFalse();
});

it('registra el detector en Negocios con su permiso, en el menú de afiliaciones y en el programador', function (): void {
    $registry = file_get_contents(app_path('Support/Filament/DepartmentNavigationPermissionRegistry.php'));
    $resource = file_get_contents(app_path('Filament/Business/Resources/PersonIdentityFindings/PersonIdentityFindingResource.php'));
    $console = file_get_contents(base_path('routes/console.php'));

    expect($registry)->toContain("PersonIdentityFindingResource::class => ['personas-duplicadas']")
        ->and($resource)->toContain("navigationGroup = 'AFILIACIONES'")
        ->and($resource)->toContain('AuthorizesDepartmentNavigation')
        ->and($console)->toContain("Schedule::job(new ScanPersonIdentityFindingsJob, 'system')->dailyAt('6:50')");

    foreach ([Affiliate::class, AffiliateCorporate::class, CompanyAssociate::class] as $model) {
        expect(file_get_contents((new ReflectionClass($model))->getFileName()))->toContain('#[ObservedBy([PersonIdentityObserver::class])]');
    }
});
