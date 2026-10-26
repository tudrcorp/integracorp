<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateObservation;
use App\Models\AffiliationDeletion;
use App\Models\Collection as CollectionModel;
use App\Models\CollectionAdjustment;
use App\Models\OperationCoordinationService;
use App\Models\PaidMembershipCorporate;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\TelemedicineCaseLogicalDeletionService;
use App\Support\Affiliations\Deletion\AffiliationDeletionInspector;
use App\Support\Affiliations\Deletion\AffiliationDeletionService;
use App\Support\Affiliations\Deletion\AffiliationDeletionSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Eliminación reversible de afiliaciones. Escribe: corre dentro de una transacción que siempre se
 * revierte, sobre afiliaciones de prueba (TEST-DEL-…) clonadas de filas reales, con personas ficticias.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();

    $this->corporateTemplate = AffiliationCorporate::query()->first();
    $this->collectionTemplate = CollectionModel::query()->first();
    $this->patientTemplate = TelemedicinePatient::query()->first();
    $this->caseTemplate = TelemedicineCase::query()->first();
    $this->serviceTemplate = OperationCoordinationService::query()->first();
    $this->paymentTemplate = PaidMembershipCorporate::query()->first();

    if (! $this->corporateTemplate || ! $this->collectionTemplate || ! $this->patientTemplate || ! $this->caseTemplate || ! $this->serviceTemplate || ! $this->paymentTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->actor = User::factory()->make(['id' => 999996, 'name' => 'Analista de prueba', 'departament' => ['SUPERADMIN'], 'email' => 'analista.del@tudrencasa.com']);
    $this->actingAs($this->actor);
});

afterEach(function (): void {
    DB::rollBack();
});

function delCorporate(object $test): AffiliationCorporate
{
    $affiliation = $test->corporateTemplate->replicate();
    $affiliation->code = 'TEST-DEL-'.Str::upper(Str::random(8));
    $affiliation->name_corporate = 'EMPRESA PRUEBA ELIMINACION';
    $affiliation->status = 'PRE-APROBADA';
    $affiliation->created_at = now()->subDays(5);
    $affiliation->save();

    return $affiliation;
}

function delAffiliate(AffiliationCorporate $owner, string $first, string $last, string $status = 'PRE-APROBADA'): AffiliateCorporate
{
    $affiliate = new AffiliateCorporate;
    $affiliate->forceFill([
        'affiliation_corporate_id' => $owner->getKey(),
        'first_name' => $first,
        'last_name' => $last,
        'nro_identificacion' => (string) random_int(80000000, 89999999),
        'birth_date' => '10/10/2010',
        'status' => $status,
    ])->save();

    return $affiliate;
}

function delPatient(object $test, AffiliationCorporate $owner, AffiliateCorporate $affiliate): TelemedicinePatient
{
    $patient = $test->patientTemplate->replicate();
    $patient->forceFill([
        'full_name' => $affiliate->first_name.' '.$affiliate->last_name,
        'nro_identificacion' => $affiliate->nro_identificacion,
        'type_affiliation' => 'CORPORATIVO',
        'code_affiliation' => $owner->code,
        'afilliation_corporate_id' => $owner->getKey(),
        'afilliation_id' => null,
        'status_affiliation' => 'ACTIVO',
        'code' => 'TEST-DEL-P-'.Str::upper(Str::random(6)),
    ])->save();

    return $patient;
}

function delCase(object $test, TelemedicinePatient $patient, string $status): TelemedicineCase
{
    $case = $test->caseTemplate->replicate();
    $case->forceFill([
        'telemedicine_patient_id' => $patient->getKey(),
        'code' => 'TEST-DEL-C-'.Str::upper(Str::random(6)),
        'status' => $status,
        'deletion_status_before' => null,
        'deletion_reason' => null,
        'deleted_by_user_id' => null,
        'deleted_by_name' => null,
        'logically_deleted_at' => null,
        'deletion_reversed_usage_ids' => null,
    ]);
    $case->created_at = now();
    $case->save();

    return $case;
}

function delCollection(object $test, AffiliationCorporate $owner, string $status = 'POR PAGAR'): CollectionModel
{
    $collection = $test->collectionTemplate->replicate();
    $collection->forceFill([
        'affiliation_code' => $owner->code,
        'status' => $status,
        'collection_invoice_number' => 'TEST-DEL-'.Str::upper(Str::random(6)),
    ])->save();

    return $collection;
}

it('bloquea eliminar una afiliación con pagos y explica por qué', function (): void {
    $owner = delCorporate($this);
    $payment = $this->paymentTemplate->replicate();
    $payment->forceFill(['affiliation_corporate_id' => $owner->getKey(), 'status' => 'APROBADO'])->save();

    $inspection = AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($owner));
    $payments = collect($inspection['checks'])->firstWhere('key', 'payments');

    expect($inspection['ok'])->toBeFalse()
        ->and($payments['passed'])->toBeFalse()
        ->and($payments['lines'][0])->toContain('aprobado')
        ->and($payments['detail'])->toContain('désela de baja');

    expect(fn () => AffiliationDeletionService::delete($owner, 'Cargada por error, la empresa la duplicó.', $this->actor))
        ->toThrow(RuntimeException::class, 'Sin pagos registrados');

    expect(AffiliationCorporate::query()->find($owner->getKey()))->not->toBeNull();
});

it('bloquea si un afiliado tiene un caso en curso o un servicio pendiente', function (): void {
    $owner = delCorporate($this);
    $affiliate = delAffiliate($owner, 'CAMILA ROSA', 'VERA LUGO');
    $patient = delPatient($this, $owner, $affiliate);
    $case = delCase($this, $patient, 'EN SEGUIMIENTO');

    $service = $this->serviceTemplate->replicate();
    $service->forceFill(['telemedicine_patient_id' => $patient->getKey(), 'telemedicine_case_id' => $case->getKey(), 'status' => 'PENDIENTE'])->save();

    $inspection = AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($owner));
    $checks = collect($inspection['checks'])->keyBy('key');

    expect($inspection['ok'])->toBeFalse()
        ->and($checks['open_cases']['passed'])->toBeFalse()
        ->and($checks['open_cases']['lines'][0])->toContain($case->code)
        ->and($checks['pending_services']['passed'])->toBeFalse()
        ->and($checks['pending_services']['lines'][0])->toContain('pendiente');
});

it('elimina la afiliación con sus afiliados, anula cuotas, desvincula pacientes y oculta casos cerrados; y lo revierte todo', function (): void {
    $owner = delCorporate($this);
    $first = delAffiliate($owner, 'CAMILA ROSA', 'VERA LUGO');
    $second = delAffiliate($owner, 'TOMAS LUIS', 'VERA LUGO', 'EXCLUIDO');
    $patient = delPatient($this, $owner, $first);
    $case = delCase($this, $patient, 'ALTA MEDICA');
    $collection = delCollection($this, $owner);
    $patientBefore = $patient->only(AffiliationDeletionService::PATIENT_LINK_COLUMNS);

    $inspection = AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($owner));

    expect($inspection['ok'])->toBeTrue()
        ->and($inspection['affiliates'])->toHaveCount(2)
        ->and($inspection['collections'])->toHaveCount(1)
        ->and($inspection['patients'][0]['hide_cases'][0]['code'])->toBe($case->code);

    $result = AffiliationDeletionService::delete($owner, 'Cargada por error: el grupo ya existía en otra afiliación.', $this->actor);

    expect(AffiliationCorporate::query()->find($owner->getKey()))->toBeNull()
        ->and(AffiliationCorporate::withTrashed()->find($owner->getKey())?->trashed())->toBeTrue()
        ->and(AffiliateCorporate::query()->whereIn('id', [$first->getKey(), $second->getKey()])->count())->toBe(0)
        ->and(CollectionModel::query()->find($collection->getKey())->status)->toBe('ANULADO')
        ->and(CollectionAdjustment::query()->where('collection_id', $collection->getKey())->latest('id')->value('mode'))->toBe(CollectionAdjustment::MODE_DELETION)
        ->and($patient->fresh()->code_affiliation)->toBeNull()
        ->and($patient->fresh()->status_affiliation)->toBe(AffiliationDeletionService::PATIENT_UNLINKED_STATUS)
        ->and(TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->getKey())->status)->toBe('ELIMINADO')
        ->and($result['deletion']->status)->toBe(AffiliationDeletion::STATUS_DELETED)
        ->and(implode(' ', $result['lines']))->toContain('2 afiliado(s)')
        ->and(AffiliationCorporateObservation::query()->where('affiliation_corporate_id', $owner->getKey())->latest('id')->value('description'))->toContain('AFILIACIÓN ELIMINADA');

    $deletion = AffiliationDeletionService::activeDeletionFor(AffiliationCorporate::withTrashed()->findOrFail($owner->getKey()));
    $restore = AffiliationDeletionService::inspectRestore($deletion);

    expect($restore['ok'])->toBeTrue();

    AffiliationDeletionService::restore($deletion, 'Se eliminó por error, la empresa confirma el grupo.', $this->actor);

    expect(AffiliationCorporate::query()->find($owner->getKey())?->status)->toBe('PRE-APROBADA')
        ->and(AffiliateCorporate::query()->find($first->getKey())?->status)->toBe('PRE-APROBADA')
        ->and(AffiliateCorporate::query()->find($second->getKey())?->status)->toBe('EXCLUIDO')
        ->and(CollectionModel::query()->find($collection->getKey())->status)->toBe('POR PAGAR')
        ->and($patient->fresh()->only(AffiliationDeletionService::PATIENT_LINK_COLUMNS))->toEqual($patientBefore)
        ->and(TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->getKey())->status)->toBe('ALTA MEDICA')
        ->and($deletion->fresh()->status)->toBe(AffiliationDeletion::STATUS_RESTORED);
});

it('no restaura si una cuota anulada cambió después', function (): void {
    $owner = delCorporate($this);
    delAffiliate($owner, 'CAMILA ROSA', 'VERA LUGO');
    $collection = delCollection($this, $owner);

    $deletion = AffiliationDeletionService::delete($owner, 'Cargada por error: el grupo ya existía en otra afiliación.', $this->actor)['deletion'];
    CollectionModel::query()->whereKey($collection->getKey())->update(['status' => 'PAGADO']);

    $restore = AffiliationDeletionService::inspectRestore($deletion);

    expect($restore['ok'])->toBeFalse()
        ->and(implode(' ', $restore['blockers']))->toContain($collection->collection_invoice_number);

    expect(fn () => AffiliationDeletionService::restore($deletion, 'Se eliminó por error, la empresa confirma.', $this->actor))
        ->toThrow(RuntimeException::class, 'No se restauró nada');

    expect(AffiliationCorporate::query()->find($owner->getKey()))->toBeNull();
});

it('no restaura si la persona quedó vigente en otra afiliación mientras tanto', function (): void {
    $owner = delCorporate($this);
    $affiliate = delAffiliate($owner, 'CAMILA ROSA', 'VERA LUGO');
    $deletion = AffiliationDeletionService::delete($owner, 'Cargada por error: el grupo ya existía en otra afiliación.', $this->actor)['deletion'];

    $other = delCorporate($this);
    $again = new AffiliateCorporate;
    $again->forceFill([
        'affiliation_corporate_id' => $other->getKey(),
        'first_name' => 'CAMILA ROSA',
        'last_name' => 'VERA LUGO',
        'nro_identificacion' => $affiliate->nro_identificacion,
        'birth_date' => '10/10/2010',
        'status' => 'ACTIVO',
    ])->save();

    $restore = AffiliationDeletionService::inspectRestore($deletion);

    expect($restore['ok'])->toBeFalse()
        ->and(implode(' ', $restore['blockers']))->toContain($other->code)
        ->and(implode(' ', $restore['blockers']))->toContain('Personas duplicadas');
});

it('pasa el paciente a la afiliación vigente de la misma persona y conserva sus casos', function (): void {
    $good = delCorporate($this);
    $good->forceFill(['status' => 'ACTIVA'])->save();
    $owner = delCorporate($this);

    $real = delAffiliate($good, 'CAMILA ROSA', 'VERA LUGO', 'ACTIVO');
    $duplicateId = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $owner->getKey(),
        'first_name' => 'VERA LUGO CAMILA ROSA',
        'last_name' => '...',
        'nro_identificacion' => $real->nro_identificacion,
        'birth_date' => '10/10/2010',
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $patient = delPatient($this, $owner, AffiliateCorporate::query()->findOrFail($duplicateId));
    $case = delCase($this, $patient, 'ALTA MEDICA');

    $inspection = AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($owner));

    expect($inspection['ok'])->toBeTrue()
        ->and($inspection['patients'][0]['action'])->toBe('reubicar')
        ->and($inspection['patients'][0]['hide_cases'])->toBe([])
        ->and($inspection['patients'][0]['kept_cases'][0]['why'])->toContain($good->code);

    AffiliationDeletionService::delete($owner, 'Duplicada: la persona está bien cargada en la otra afiliación.', $this->actor);

    expect($patient->fresh()->code_affiliation)->toBe($good->code)
        ->and((int) $patient->fresh()->afilliation_corporate_id)->toBe((int) $good->getKey())
        ->and(TelemedicineCase::query()->find($case->getKey())?->status)->toBe('ALTA MEDICA');
});

it('exige motivo y el permiso', function (): void {
    $owner = delCorporate($this);

    expect(fn () => AffiliationDeletionService::delete($owner, 'corto', $this->actor))->toThrow(RuntimeException::class, 'motivo');

    $analyst = User::factory()->make(['id' => 999995, 'departament' => ['NEGOCIOS'], 'email' => 'sinpermiso.del@tudrencasa.com']);

    expect(AffiliationDeletionService::userCan($analyst))->toBeFalse()
        ->and(fn () => AffiliationDeletionService::delete($owner, 'Cargada por error: el grupo ya existía.', $analyst))->toThrow(RuntimeException::class, 'permiso');
});

it('genera el código de una afiliación nueva contando también las eliminadas', function (): void {
    $sources = collect(Symfony\Component\Finder\Finder::create()->files()->in(app_path())->name('*.php'))
        ->filter(fn ($file): bool => preg_match("/\\b(Affiliation|AffiliationCorporate)::max\\('id'\\)/", $file->getContents()) === 1)
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($sources)->toBe([])
        ->and(Affiliation::withTrashed()->max('id'))->toBeGreaterThanOrEqual((int) Affiliation::query()->max('id'));
});

it('pone eliminar y restaurar en las tablas de Negocios y Administración con el filtro de eliminadas', function (): void {
    foreach ([
        'Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php',
        'Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
        'Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php',
        'Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    ] as $path) {
        expect(file_get_contents(app_path($path)))
            ->toContain('...AffiliationDeletionActions::recordActions()')
            ->toContain('AffiliationDeletionActions::deletedFilter()')
            ->toContain('$record->trashed()');
    }

    expect(App\Support\Filament\BusinessFilamentActionPermissionRegistry::modulesForSlug('eliminar-afiliaciones'))
        ->toBe(['NEGOCIOS', 'ADMINISTRACION']);
});

it('muestra eliminar en la tabla de Negocios y restaurar en la vista de eliminadas', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $owner = delCorporate($this);

    Livewire\Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->assertSuccessful()
        ->searchTable($owner->code)
        ->assertTableActionVisible('deleteAffiliation', $owner)
        ->assertTableActionHidden('restoreAffiliation', $owner);

    AffiliationDeletionService::delete($owner, 'Cargada por error: el grupo ya existía en otra afiliación.', $this->actor);

    Livewire\Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->filterTable('eliminadas', true)
        ->searchTable($owner->code)
        ->assertSee($owner->code)
        ->assertTableActionHidden('deleteAffiliation', AffiliationCorporate::withTrashed()->findOrFail($owner->getKey()))
        ->assertTableActionVisible('restoreAffiliation', AffiliationCorporate::withTrashed()->findOrFail($owner->getKey()));
});

it('la eliminación masiva solo elimina las que pasan la revisión y deja la nota en cada una', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $free = delCorporate($this);
    $paid = delCorporate($this);
    $payment = $this->paymentTemplate->replicate();
    $payment->forceFill(['affiliation_corporate_id' => $paid->getKey(), 'status' => 'APROBADO'])->save();

    Livewire\Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->mountTableBulkAction('deleteAffiliations', [$free, $paid])
        ->set('mountedActions.0.data.reason', 'Afiliaciones de prueba cargadas por error en la capacitación.')
        ->set('mountedActions.0.data.confirmed', true)
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified();

    expect(AffiliationCorporate::query()->find($free->getKey()))->toBeNull()
        ->and(AffiliationCorporate::query()->find($paid->getKey()))->not->toBeNull()
        ->and(AffiliationDeletion::query()->where('affiliation_code', $free->code)->value('reason'))->toBe('Afiliaciones de prueba cargadas por error en la capacitación.');

    $preview = (new ReflectionMethod(App\Filament\Shared\Affiliations\AffiliationDeletionActions::class, 'bulkPreviewHtml'))
        ->invoke(null, new Illuminate\Database\Eloquent\Collection([AffiliationCorporate::withTrashed()->findOrFail($free->getKey())->restore() ? AffiliationCorporate::query()->findOrFail($free->getKey()) : null, $paid]));

    expect((string) $preview)->toContain('1 de 2 se pueden eliminar.')
        ->and((string) $preview)->toContain('no se eliminará: sin pagos registrados');
});

it('no quedan botones de borrado sin validaciones sobre afiliaciones en ningún panel', function (): void {
    $files = collect(Symfony\Component\Finder\Finder::create()->files()->in(app_path('Filament'))->name('*.php'))
        ->filter(fn ($file): bool => preg_match('#(^|/)(Affiliations|AffiliationCorporates)/(Tables|Pages)/#', $file->getRelativePathname()) === 1
            || str_ends_with($file->getRelativePathname(), 'CorporateAffiliatesRelationManager.php'))
        ->filter(fn ($file): bool => preg_match('/^\s*(DeleteBulkAction|DeleteAction)::make\(\)/m', $file->getContents()) === 1)
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($files)->toBe([]);
});
