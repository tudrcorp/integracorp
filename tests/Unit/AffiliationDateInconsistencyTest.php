<?php

declare(strict_types=1);

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationDateCorrection;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\AffiliationDateInconsistencyEvent as Event;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Models\User;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyDetector;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyResolver;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Detector de inconsistencias de fechas. Escribe: corre dentro de una transacción que siempre se
 * revierte, sobre copias de prueba (TEST-ADI-…) clonadas de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->collectionTemplate = Collection::query()->first();
    $this->corporateTemplate = AffiliationCorporate::query()->first();
    $this->individualTemplate = Affiliation::query()->first();

    if (! $this->collectionTemplate || ! $this->corporateTemplate || ! $this->individualTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->actor = User::factory()->make(['name' => 'Analista de prueba', 'departament' => ['SUPERADMIN']]);
});

afterEach(function (): void {
    DB::rollBack();
});

function inconsistencyTestCorporate(object $test, string $activated, string $effective, string $frequency = 'TRIMESTRAL'): AffiliationCorporate
{
    $affiliation = $test->corporateTemplate->replicate();
    $affiliation->code = 'TEST-ADI-C-'.Str::upper(Str::random(6));
    $affiliation->status = 'ACTIVA';
    $affiliation->payment_frequency = $frequency;
    $affiliation->activated_at = $activated;
    $affiliation->effective_date = $effective;
    $affiliation->save();

    return $affiliation;
}

function inconsistencyTestIndividual(object $test, string $activated, string $effective, string $frequency = 'SEMESTRAL'): Affiliation
{
    $affiliation = $test->individualTemplate->replicate();
    $affiliation->code = 'TEST-ADI-I-'.Str::upper(Str::random(6));
    $affiliation->status = 'ACTIVA';
    $affiliation->payment_frequency = $frequency;
    $affiliation->activated_at = $activated;
    $affiliation->effective_date = $effective;
    $affiliation->save();

    return $affiliation;
}

function inconsistencyTestAviso(object $test, object $affiliation, string $include, string $due): Collection
{
    $collection = $test->collectionTemplate->replicate();
    $collection->affiliation_code = $affiliation->code;
    $collection->include_date = $include;
    $collection->next_payment_date = $due;
    $collection->status = 'POR PAGAR';
    $collection->payment_frequency = $affiliation->payment_frequency;
    $collection->collection_invoice_number = 'T-'.Str::upper(Str::random(6));
    $collection->save();

    return $collection;
}

/**
 * @return array<string, array<string, mixed>>
 */
function inconsistencyKinds(object $affiliation): array
{
    $byKind = [];

    foreach (DateInconsistencyDetector::inspect($affiliation) as $finding) {
        $byKind[$finding['kind']] = $finding;
    }

    return $byKind;
}

it('detecta la vigencia guardada como fin del año y sugiere el inicio según los avisos', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');

    $finding = inconsistencyKinds($affiliation)[Kind::VigenciaComoFin->value] ?? null;

    expect($finding)->not->toBeNull()
        ->and($finding['repair_mode'])->toBe(Kind::REPAIR_AUTO)
        ->and($finding['explanation'])->toContain('fecha de FIN')
        ->and($finding['suggestions'][0]['date'])->toBe('10/11/2025')
        ->and($finding['facts'])->toHaveKey('Inicio del ciclo según los avisos');
});

it('detecta vigencia y avisos que no coinciden y deja elegir entre las dos fechas', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '26/08/2026', '26/08/2026');
    inconsistencyTestAviso($this, $affiliation, '20/07/2026', '20/10/2026');

    $finding = inconsistencyKinds($affiliation)[Kind::FechasNoCoinciden->value] ?? null;

    expect($finding)->not->toBeNull()
        ->and($finding['repair_mode'])->toBe(Kind::REPAIR_DATE)
        ->and(array_column($finding['suggestions'], 'date'))->toBe(['20/07/2026', '26/08/2026']);
});

it('explica cuántos días está corrido un aviso fuera de calendario y no lo repara solo', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '20/07/2026', '20/07/2026');
    inconsistencyTestAviso($this, $affiliation, '20/07/2026', '23/10/2026');

    $finding = inconsistencyKinds($affiliation)[Kind::AvisoFueraDeCalendario->value] ?? null;

    expect($finding)->not->toBeNull()
        ->and($finding['repair_mode'])->toBe(Kind::REPAIR_MANUAL)
        ->and($finding['explanation'])->toContain('le corresponde el 20/10/2026 (corrido 3 día(s) después)');
});

it('detecta una vigencia imposible sin sugerir la misma fecha cuestionada', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '04/02/2026', '04/02/2029');

    $finding = inconsistencyKinds($affiliation)[Kind::VigenciaAbsurda->value] ?? null;

    expect($finding)->not->toBeNull()
        ->and(array_column($finding['suggestions'], 'date'))->not->toContain('04/02/2029');
});

it('no marca como imposible una póliza antigua renovada fuera del historial', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '17/02/2023', '17/02/2026');

    expect(inconsistencyKinds($affiliation))->not->toHaveKey(Kind::VigenciaAbsurda->value);
});

it('detecta una emisión cargada lejos del primer pago solo si la afiliación se creó con esa fecha', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '20/07/2026', '20/07/2026');
    $affiliation->forceFill(['created_at' => '2026-07-18 10:00:00'])->save();

    $payment = PaidMembership::query()->first()?->replicate();

    if ($payment === null) {
        $this->markTestSkipped('No hay pagos para clonar.');
    }

    $payment->affiliation_id = $affiliation->id;
    $payment->status = 'APROBADO';
    $payment->created_at = '2026-09-07 10:00:00';
    $payment->save();

    expect(inconsistencyKinds($affiliation))->toHaveKey(Kind::EmisionLejosDelPrimerPago->value);

    $affiliation->forceFill(['created_at' => '2026-09-07 09:00:00'])->save();

    expect(inconsistencyKinds($affiliation->fresh()))->not->toHaveKey(Kind::EmisionLejosDelPrimerPago->value);
});

it('el escáner abre, no duplica y cierra sola la inconsistencia cuando los datos quedan bien', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');

    expect(DateInconsistencyScanner::scanAffiliation($affiliation)['opened'])->toBeGreaterThanOrEqual(1);
    expect(DateInconsistencyScanner::scanAffiliation($affiliation)['opened'])->toBe(0);

    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaComoFin->value)->sole();

    expect($row->status)->toBe(Inconsistency::STATUS_OPEN)
        ->and($row->events()->where('action', Event::DETECTED)->count())->toBe(1);

    $affiliation->forceFill(['effective_date' => '10/11/2025'])->save();
    DateInconsistencyScanner::scanAffiliation($affiliation->fresh());

    expect($row->fresh()->status)->toBe(Inconsistency::STATUS_RESOLVED)
        ->and($row->events()->where('action', Event::RESOLVED)->count())->toBe(1);
});

it('«no es una inconsistencia» exige nota, se mantiene si nada cambia y se reabre si los datos cambian', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '04/02/2026', '04/02/2029');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaAbsurda->value)->sole();

    expect(fn () => DateInconsistencyResolver::dismiss($row, 'corta', $this->actor))->toThrow(RuntimeException::class, 'nota');

    DateInconsistencyResolver::dismiss($row, 'Negocios confirma la fecha con el contrato firmado', $this->actor);
    DateInconsistencyScanner::scanAffiliation($affiliation);

    expect($row->fresh()->status)->toBe(Inconsistency::STATUS_DISMISSED);

    $affiliation->forceFill(['effective_date' => '04/03/2029'])->save();
    DateInconsistencyScanner::scanAffiliation($affiliation->fresh());

    expect($row->fresh()->status)->toBe(Inconsistency::STATUS_OPEN)
        ->and($row->events()->where('action', Event::REOPENED)->count())->toBe(1);
});

it('marca varias como no inconsistencia con una nota, todo o nada, y se pueden reabrir', function (): void {
    $first = inconsistencyTestCorporate($this, '04/02/2026', '04/02/2029');
    $second = inconsistencyTestCorporate($this, '05/02/2026', '05/02/2029');
    DateInconsistencyScanner::scanAffiliation($first);
    DateInconsistencyScanner::scanAffiliation($second);
    $ids = Inconsistency::query()->whereIn('affiliation_code', [$first->code, $second->code])->pluck('id')->all();

    expect(DateInconsistencyResolver::dismissMany($ids, 'Revisadas con Negocios: fechas de contrato correctas', $this->actor))->toBe(count($ids));
    expect(fn () => DateInconsistencyResolver::dismissMany($ids, 'Segundo intento sobre las mismas', $this->actor))->toThrow(RuntimeException::class);

    $row = Inconsistency::query()->findOrFail($ids[0]);
    DateInconsistencyResolver::reopen($row, 'Se reabre para revisarla otra vez', $this->actor);

    expect($row->fresh()->status)->toBe(Inconsistency::STATUS_OPEN);
});

it('el resumen previo es exacto y no guarda nada', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    $aviso = inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaComoFin->value)->sole();
    $corrections = AffiliationDateCorrection::query()->count();

    $simulation = DateInconsistencyResolver::simulate($row, '10/11/2025', $this->actor);

    expect($simulation['ok'])->toBeTrue()
        ->and($simulation['fingerprint'])->not->toBeNull()
        ->and($simulation['effective'])->toBe('10/11/2025')
        ->and(implode(' | ', $simulation['lines']))->toContain('fecha de vigencia: 10/11/2026 → 10/11/2025')
        ->and($affiliation->fresh()->effective_date)->toBe('10/11/2026')
        ->and($aviso->fresh()->next_payment_date)->toBe('10/05/2026')
        ->and(AffiliationDateCorrection::query()->count())->toBe($corrections);
});

it('repara con nota, deja punto de restauración, cierra la inconsistencia y se puede revertir', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaComoFin->value)->sole();
    $simulation = DateInconsistencyResolver::simulate($row, '10/11/2025', $this->actor);

    $result = DateInconsistencyResolver::repair($row, '10/11/2025', 'Regla vieja del primer pago: la vigencia es el inicio', (string) $simulation['fingerprint'], $this->actor);

    expect($row->fresh()->status)->toBe(Inconsistency::STATUS_REPAIRED)
        ->and($row->fresh()->correction_id)->toBe($result['correction']->id)
        ->and($affiliation->fresh()->effective_date)->toBe('10/11/2025')
        ->and($row->events()->where('action', Event::REPAIRED)->first()?->payload['lines'])->toBe($simulation['lines']);

    DateInconsistencyResolver::revert($row->fresh(), 'Se reparó con la fecha equivocada', $this->actor);

    expect($affiliation->fresh()->effective_date)->toBe('10/11/2026')
        ->and($row->fresh()->status)->toBe(Inconsistency::STATUS_OPEN)
        ->and($result['correction']->fresh()->status)->toBe(AffiliationDateCorrection::STATUS_ROLLED_BACK);
});

it('no aplica la reparación si los datos cambiaron después del resumen', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    $aviso = inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaComoFin->value)->sole();
    $simulation = DateInconsistencyResolver::simulate($row, '10/11/2025', $this->actor);

    DB::table('collections')->where('id', $aviso->id)->update(['include_date' => '10/11/2025 ']);

    expect(fn () => DateInconsistencyResolver::repair($row, '10/11/2025', 'Intento con datos cambiados', (string) $simulation['fingerprint'], $this->actor))
        ->toThrow(RuntimeException::class, 'cambiaron');
    expect($affiliation->fresh()->effective_date)->toBe('10/11/2026')
        ->and($row->fresh()->status)->toBe(Inconsistency::STATUS_OPEN);
});

it('las inconsistencias manuales no se reparan desde aquí', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '20/07/2026', '20/07/2026');
    inconsistencyTestAviso($this, $affiliation, '20/07/2026', '23/10/2026');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::AvisoFueraDeCalendario->value)->sole();

    expect(DateInconsistencyResolver::canRepair($row))->toBeFalse();
    expect(fn () => DateInconsistencyResolver::repair($row, '20/07/2026', 'Intento sobre una manual', 'x', $this->actor))
        ->toThrow(RuntimeException::class);
});

it('exige el permiso propio para gestionar inconsistencias', function (): void {
    $affiliation = inconsistencyTestCorporate($this, '04/02/2026', '04/02/2029');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->firstOrFail();
    $withoutPermission = User::factory()->make(['departament' => ['ADMINISTRACION']]);

    expect(fn () => DateInconsistencyResolver::dismiss($row, 'Nota suficientemente larga', $withoutPermission))->toThrow(RuntimeException::class, 'permiso')
        ->and(fn () => DateInconsistencyResolver::simulate($row, '04/02/2026', $withoutPermission))->toThrow(RuntimeException::class, 'permiso');
});

it('el detector vive en Administración con su menú, su permiso y la revisión diaria', function (): void {
    $root = dirname(__DIR__, 2);

    expect((string) file_get_contents($root.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php'))
        ->toContain("AffiliationDateInconsistencyResource::class => ['inconsistencias-de-fechas']")
        ->and((string) file_get_contents($root.'/app/Support/Filament/BusinessFilamentActionPermissionRegistry.php'))
        ->toContain("'reparar-inconsistencias-de-fechas'")
        ->and((string) file_get_contents($root.'/routes/console.php'))
        ->toContain("Schedule::job(new ScanAffiliationDateInconsistenciesJob, 'system')->dailyAt('6:45')");
});

it('la lista y el detalle se renderizan con la explicación completa', function (): void {
    $affiliation = inconsistencyTestIndividual($this, '10/11/2025', '10/11/2026');
    inconsistencyTestAviso($this, $affiliation, '10/11/2025', '10/05/2026');
    DateInconsistencyScanner::scanAffiliation($affiliation);
    $row = Inconsistency::query()->where('affiliation_code', $affiliation->code)->where('kind', Kind::VigenciaComoFin->value)->sole();

    $user = User::query()->whereJsonContains('departament', 'SUPERADMIN')->first();

    if (! $user instanceof User) {
        $this->markTestSkipped('No hay un usuario SUPERADMIN para entrar al panel.');
    }

    Filament\Facades\Filament::setCurrentPanel('administration');
    $this->actingAs($user);

    Livewire\Livewire::test(App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages\ListAffiliationDateInconsistencies::class)
        ->assertOk()
        ->assertSee($affiliation->code)
        ->assertSee('Vigencia guardada como fin del año');

    Livewire\Livewire::test(App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages\ViewAffiliationDateInconsistency::class, ['record' => $row->getKey()])
        ->assertOk()
        ->assertSee('Qué está mal')
        ->assertSee('Por qué importa')
        ->assertSee('Cómo se corrige')
        ->assertSee('Inicio del ciclo según los avisos')
        ->assertActionVisible('repair')
        ->assertActionVisible('dismiss');
});
