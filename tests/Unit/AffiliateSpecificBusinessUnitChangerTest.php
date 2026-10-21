<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateBusinessUnitChange;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\AffiliationCorporateAffiliateBusinessContextSynchronizer;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\Affiliations\AffiliateSpecificBusinessUnitChanger;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Trabajan sobre afiliados reales, siempre dentro de una transacción que se
 * revierte: no queda nada escrito.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function businessUnitActor(): User
{
    return User::factory()->make(['id' => 987700, 'name' => 'Analista Unidad Pest']);
}

function businessUnitNote(): string
{
    return 'Cambio de sede confirmado por RR. HH. en prueba automatizada.';
}

function individualAffiliateWithPatient(): ?Affiliate
{
    $id = DB::table('affiliates as a')
        ->join('affiliations as o', 'o.id', '=', 'a.affiliation_id')
        ->join('telemedicine_patients as tp', function ($join): void {
            $join->on('tp.afilliation_id', '=', 'o.id')
                ->on('tp.nro_identificacion', '=', 'a.nro_identificacion')
                ->on('tp.code_affiliation', '=', 'o.code')
                ->where('tp.type_affiliation', '=', 'INDIVIDUAL');
        })
        ->value('a.id');

    return $id ? Affiliate::query()->find($id) : null;
}

function corporateAffiliateWithPatient(): ?AffiliateCorporate
{
    $id = DB::table('affiliate_corporates as ac')
        ->join('affiliation_corporates as o', 'o.id', '=', 'ac.affiliation_corporate_id')
        ->join('telemedicine_patients as tp', function ($join): void {
            $join->on('tp.afilliation_corporate_id', '=', 'o.id')
                ->on('tp.nro_identificacion', '=', 'ac.nro_identificacion')
                ->on('tp.code_affiliation', '=', 'o.code')
                ->where('tp.type_affiliation', '=', 'CORPORATIVO');
        })
        ->value('ac.id');

    return $id ? AffiliateCorporate::query()->find($id) : null;
}

it('normaliza el valor y exige una nota que explique el cambio', function (): void {
    expect(AffiliateSpecificBusinessUnitChanger::normalize('  sede   valencia '))->toBe('SEDE VALENCIA')
        ->and(AffiliateSpecificBusinessUnitChanger::normalize('   '))->toBeNull()
        ->and(AffiliateSpecificBusinessUnitChanger::normalize(null))->toBeNull();

    $affiliate = individualAffiliateWithPatient() ?? Affiliate::query()->firstOrFail();

    expect(fn () => AffiliateSpecificBusinessUnitChanger::change([$affiliate], 'X', false, 'corta', businessUnitActor()))
        ->toThrow(InvalidArgumentException::class, 'al menos 15')
        ->and(fn () => AffiliateSpecificBusinessUnitChanger::change([], 'X', false, businessUnitNote(), businessUnitActor()))
        ->toThrow(InvalidArgumentException::class, 'No hay afiliados')
        ->and(fn () => AffiliateSpecificBusinessUnitChanger::change([$affiliate, AffiliateCorporate::query()->firstOrFail()], 'X', false, businessUnitNote(), businessUnitActor()))
        ->toThrow(InvalidArgumentException::class, 'mezclar');

    expect(AffiliateBusinessUnitChange::query()->where('affiliate_id', $affiliate->id)->exists())->toBeFalse();
});

it('cambia la unidad de un afiliado individual, la fija, actualiza su paciente y deja traza', function (): void {
    $affiliate = individualAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado individual con paciente de telemedicina ligado.');
    }

    $before = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($affiliate->specific_business_unit);
    $value = 'SEDE PEST '.mb_strtoupper(uniqid());

    $result = AffiliateSpecificBusinessUnitChanger::change([$affiliate], mb_strtolower($value), false, businessUnitNote(), businessUnitActor());

    $affiliate->refresh();
    $patients = TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate);

    expect($result['changed'])->toBe(1)
        ->and($result['patients'])->toBeGreaterThanOrEqual(1)
        ->and($affiliate->specific_business_unit)->toBe($value)
        ->and(AffiliateSpecificBusinessUnitChanger::isLocked($affiliate))->toBeTrue()
        ->and($affiliate->specific_business_unit_locked_by)->toBe('Analista Unidad Pest')
        ->and($patients->every(fn (TelemedicinePatient $patient): bool => $patient->fresh()->specific_business_unit === $value))->toBeTrue();

    $change = AffiliateBusinessUnitChange::query()->where('batch_id', $result['batch_id'])->sole();
    expect($change->affiliate_type)->toBe('INDIVIDUAL')
        ->and((int) $change->affiliate_id)->toBe((int) $affiliate->id)
        ->and((int) $change->affiliation_id)->toBe((int) $affiliate->affiliation_id)
        ->and($change->before_value)->toBe($before)
        ->and($change->after_value)->toBe($value)
        ->and($change->note)->toBe(businessUnitNote())
        ->and($change->inherited)->toBeFalse()
        ->and($change->telemedicine_patient_ids)->toBe($patients->pluck('id')->map(fn ($id): int => (int) $id)->all())
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_AFFILIATE_SPECIFIC_BUSINESS_UNIT_CHANGED')->where('response', 'like', '%'.$result['batch_id'].'%')->exists())->toBeTrue();

    // Repetir el mismo valor no hace nada ni deja otra traza.
    $again = AffiliateSpecificBusinessUnitChanger::change([$affiliate], $value, false, businessUnitNote(), businessUnitActor());
    expect($again['changed'])->toBe(0)
        ->and($again['unchanged'])->toBe(1)
        ->and(AffiliateBusinessUnitChange::query()->where('batch_id', $again['batch_id'])->exists())->toBeFalse();
});

it('la sincronización de la afiliación respeta el valor fijado a mano y pisa los demás', function (): void {
    $affiliate = individualAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado individual con paciente de telemedicina ligado.');
    }

    $owner = $affiliate->affiliation;
    $sibling = $affiliate->replicate();
    $sibling->forceFill(['full_name' => 'HERMANO PEST', 'nro_identificacion' => 'V-PEST-'.uniqid()])->save();

    AffiliateSpecificBusinessUnitChanger::change([$affiliate], 'SEDE FIJA PEST', false, businessUnitNote(), businessUnitActor());

    app(AffiliationAffiliateBusinessContextSynchronizer::class)->sync($owner, $owner->business_unit_id ?: 1, $owner->business_line_id ?: 1, 'VALOR DE LA AFILIACION');

    expect($affiliate->fresh()->specific_business_unit)->toBe('SEDE FIJA PEST')
        ->and($sibling->fresh()->specific_business_unit)->toBe('VALOR DE LA AFILIACION');
});

it('dejar sin unidad la fija vacía y heredar vuelve al valor de la afiliación', function (): void {
    $affiliate = individualAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado individual con paciente de telemedicina ligado.');
    }

    /** @var Affiliation $owner */
    $owner = $affiliate->affiliation;
    $owner->forceFill(['specific_business_unit' => 'UNIDAD DUEÑA PEST'])->save();

    AffiliateSpecificBusinessUnitChanger::change([$affiliate], null, false, businessUnitNote(), businessUnitActor());
    $affiliate->refresh();

    // Vacía a propósito: al volver a asociar el paciente no hereda la de la afiliación.
    expect($affiliate->specific_business_unit)->toBeNull()
        ->and(AffiliateSpecificBusinessUnitChanger::isLocked($affiliate))->toBeTrue()
        ->and(AffiliateSpecificBusinessUnitChanger::effectiveValueFor($affiliate, $owner))->toBeNull();

    $result = AffiliateSpecificBusinessUnitChanger::change([$affiliate], 'IGNORADO', true, businessUnitNote(), businessUnitActor());
    $affiliate->refresh();

    expect($affiliate->specific_business_unit)->toBe('UNIDAD DUEÑA PEST')
        ->and(AffiliateSpecificBusinessUnitChanger::isLocked($affiliate))->toBeFalse()
        ->and($affiliate->specific_business_unit_locked_by)->toBeNull()
        ->and(AffiliateBusinessUnitChange::query()->where('batch_id', $result['batch_id'])->sole()->inherited)->toBeTrue()
        ->and(TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate)->first()?->fresh()->specific_business_unit)->toBe('UNIDAD DUEÑA PEST');
});

it('no toca a un paciente que hoy pertenece a otra afiliación', function (): void {
    $affiliate = individualAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado individual con paciente de telemedicina ligado.');
    }

    $patient = TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate)->firstOrFail();
    $patient->forceFill(['code_affiliation' => 'OTRA-AFILIACION-PEST', 'specific_business_unit' => 'INTACTA'])->save();

    $result = AffiliateSpecificBusinessUnitChanger::change([$affiliate], 'NUEVA PEST', false, businessUnitNote(), businessUnitActor());

    expect($patient->fresh()->specific_business_unit)->toBe('INTACTA')
        ->and(AffiliateBusinessUnitChange::query()->where('batch_id', $result['batch_id'])->sole()->telemedicine_patient_ids ?? [])->not->toContain((int) $patient->id);
});

it('en corporativos la sincronización de plan y la de afiliación no pisan el valor fijado', function (): void {
    $affiliate = corporateAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado corporativo con paciente de telemedicina ligado.');
    }

    $owner = $affiliate->affiliationCorporate;
    $result = AffiliateSpecificBusinessUnitChanger::change([$affiliate], 'SUCURSAL PEST', false, businessUnitNote(), businessUnitActor());
    $affiliate->refresh();

    expect($result['changed'])->toBe(1)
        ->and(AffiliateBusinessUnitChange::query()->where('batch_id', $result['batch_id'])->sole()->affiliate_type)->toBe('CORPORATIVO')
        ->and(TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate)->first()?->fresh()->specific_business_unit)->toBe('SUCURSAL PEST');

    // Para la sincronización de planes, la unidad fijada a mano no es una diferencia.
    $owner->forceFill(['specific_business_unit' => 'OTRA COSA'])->save();
    $sameUnitAndLine = (int) $affiliate->business_unit_id === (int) $owner->business_unit_id
        && (int) $affiliate->business_line_id === (int) $owner->business_line_id;
    if ($sameUnitAndLine) {
        expect(CorporateAffiliatePlanSynchronizer::businessContextIsSynced($owner, $affiliate))->toBeTrue();
    }

    app(AffiliationCorporateAffiliateBusinessContextSynchronizer::class)->sync($owner, $owner->business_unit_id ?: 1, $owner->business_line_id ?: 1, 'OTRA COSA');

    expect($affiliate->fresh()->specific_business_unit)->toBe('SUCURSAL PEST');
});

it('las tablas de afiliados de Negocios usan la acción con nota y traza', function (): void {
    $root = dirname(__DIR__, 2);

    foreach ([
        '/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
        '/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
    ] as $path) {
        expect(file_get_contents($root.$path))
            ->toContain('use ManagesAffiliateSpecificBusinessUnit;')
            ->toContain('$this->changeSpecificBusinessUnitAction()')
            ->toContain('$this->changeSpecificBusinessUnitBulkAction()')
            ->toContain('$this->specificBusinessUnitHistoryAction()')
            ->toContain('$this->specificBusinessUnitColumn()');
    }

    $concern = file_get_contents($root.'/app/Filament/Business/Concerns/ManagesAffiliateSpecificBusinessUnit.php');
    expect($concern)
        ->toContain("Textarea::make('note')")
        ->toContain('->required()')
        ->toContain("userHasModule(\$user, 'NEGOCIOS')")
        ->toContain('abort_unless(');
});

function businessUnitUser(array $departments): User
{
    return User::factory()->make([
        'id' => 987701,
        'name' => 'Analista Negocios Pest',
        'email' => 'negocios-pest-'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        'is_business_admin' => 0,
    ]);
}

it('un analista de Negocios cambia la unidad desde la tabla corporativa con nota obligatoria', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $affiliate = corporateAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado corporativo con paciente de telemedicina ligado.');
    }

    $action = Filament\Actions\Testing\TestAction::make('change_specific_business_unit')->table($affiliate);

    $component = Livewire\Livewire::actingAs(businessUnitUser(['NEGOCIOS']))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager::class, [
            'ownerRecord' => $affiliate->affiliationCorporate,
            'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class,
        ])
        ->assertOk()
        ->searchTable((string) $affiliate->nro_identificacion)
        ->assertActionVisible($action);

    // Sin nota no se guarda.
    $component
        ->mountAction($action)
        ->set('mountedActions.0.data.mode', 'set')
        ->set('mountedActions.0.data.value', 'sede tabla pest')
        ->set('mountedActions.0.data.note', 'corta')
        ->callMountedAction()
        ->assertHasActionErrors(['note']);

    expect($affiliate->fresh()->specific_business_unit)->not->toBe('SEDE TABLA PEST');

    $component
        ->set('mountedActions.0.data.note', businessUnitNote())
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Unidad específica actualizada');

    expect($affiliate->fresh()->specific_business_unit)->toBe('SEDE TABLA PEST')
        ->and(AffiliateBusinessUnitChange::query()->where('affiliate_type', 'CORPORATIVO')->where('affiliate_id', $affiliate->id)->where('actor_name', 'Analista Negocios Pest')->exists())->toBeTrue();
});

it('un usuario que no es de Negocios no ve la acción', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $affiliate = corporateAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado corporativo con paciente de telemedicina ligado.');
    }

    Livewire\Livewire::actingAs(businessUnitUser(['OPERACIONES']))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager::class, [
            'ownerRecord' => $affiliate->affiliationCorporate,
            'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class,
        ])
        ->searchTable((string) $affiliate->nro_identificacion)
        ->assertActionHidden(Filament\Actions\Testing\TestAction::make('change_specific_business_unit')->table($affiliate));
});

it('en la tabla individual el analista ve la acción aunque no sea administrador de negocios', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $affiliate = individualAffiliateWithPatient();
    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliado individual con paciente de telemedicina ligado.');
    }

    Livewire\Livewire::actingAs(businessUnitUser(['NEGOCIOS']))
        ->test(App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager::class, [
            'ownerRecord' => $affiliate->affiliation,
            'pageClass' => App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation::class,
        ])
        ->assertOk()
        ->assertActionVisible(Filament\Actions\Testing\TestAction::make('change_specific_business_unit')->table($affiliate))
        ->assertActionHidden(Filament\Actions\Testing\TestAction::make('changet_status')->table($affiliate))
        ->assertActionVisible(Filament\Actions\Testing\TestAction::make('change_specific_business_unit_bulk')->table()->bulk());
});
