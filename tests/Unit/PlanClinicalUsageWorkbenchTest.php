<?php

declare(strict_types=1);

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Enums\PlanPricingMode;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use App\Models\PlanClinicalUsageEvent;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\ClinicalEntitlements\PlanClinicalUsageConflictException;
use App\Support\ClinicalEntitlements\PlanClinicalUsageTrace;
use App\Support\ClinicalEntitlements\PlanClinicalUsageWorkbench;
use App\Support\Plans\PlanCodeGenerator;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escriben en la base: siempre dentro de una transacción que se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function clinicalWorkbenchPlan(string $name, PlanPricingMode $mode = PlanPricingMode::Paquete): Plan
{
    return Plan::query()->create([
        'code' => PlanCodeGenerator::next(),
        'description' => $name.' '.uniqid(),
        'business_unit_id' => 1,
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => $mode->value,
        'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
    ]);
}

function clinicalWorkbenchBenefit(string $name): Benefit
{
    return Benefit::query()->create([
        'description' => mb_strtoupper($name).' '.uniqid(),
        'status' => 'ACTIVO',
        'created_by' => 'pest',
    ]);
}

/**
 * @param  array<string, mixed>  $config
 */
function clinicalWorkbenchSetting(Plan $plan, Benefit $benefit, array $config): void
{
    BenefitPlan::query()->firstOrCreate(['plan_id' => $plan->id, 'benefit_id' => $benefit->id]);

    PlanBenefitClinicalSetting::query()->create([
        'plan_id' => $plan->id,
        'benefit_id' => $benefit->id,
        'created_by' => 'pest',
        'updated_by' => 'pest',
    ] + $config);
}

function clinicalWorkbenchActor(): User
{
    return User::factory()->make(['id' => 987654, 'name' => 'Analista Pest']);
}

/**
 * @param  array<string, array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function clinicalWorkbenchRowFor(array $rows, int $benefitId): array
{
    foreach ($rows as $row) {
        if ((int) ($row['benefit_id'] ?? 0) === $benefitId) {
            return $row;
        }
    }

    throw new RuntimeException('Fila no encontrada');
}

function clinicalWorkbenchMacroId(): int
{
    return (int) TelemedicineServiceList::query()->where('level', '1')->value('id');
}

it('reconoce filas completas, pendientes y las que no aplican en consulta', function (): void {
    expect(PlanClinicalUsageWorkbench::rowIsComplete(['applies_clinically' => false]))->toBeTrue()
        ->and(PlanClinicalUsageWorkbench::rowIsComplete(['applies_clinically' => true, 'channel' => 'TYPE_1', 'quota_scope' => 'UNLIMITED']))->toBeFalse()
        ->and(PlanClinicalUsageWorkbench::rowMissingHint(['applies_clinically' => true, 'channel' => 'TYPE_1', 'quota_scope' => 'UNLIMITED']))->toBe('Elija el servicio macro')
        ->and(PlanClinicalUsageWorkbench::rowMissingHint(['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_CONTRACT']))->toBe('Indique la cantidad del cupo')
        ->and(PlanClinicalUsageWorkbench::rowIsComplete(['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_CONTRACT', 'quota' => 3]))->toBeTrue()
        ->and(PlanClinicalUsageWorkbench::rowMissingHint(['applies_clinically' => '1']))->toBe('Elija el servicio clínico');

    $summary = PlanClinicalUsageWorkbench::summarize([
        ['applies_clinically' => false, 'origin' => 'plan'],
        ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED', 'origin' => 'plan'],
        ['applies_clinically' => true, 'origin' => 'link'],
        'basura',
    ]);

    expect($summary)->toBe(['total' => 3, 'complete' => 2, 'pending' => 1, 'not_clinical' => 1, 'new' => 1]);
});

it('copia solo los pendientes, informa lo que queda igual y ofrece lo que solo tiene el origen', function (): void {
    $macro = clinicalWorkbenchMacroId();
    $tele = clinicalWorkbenchBenefit('telemedicina');
    $meds = clinicalWorkbenchBenefit('medicamentos');
    $funeral = clinicalWorkbenchBenefit('funerario');
    $lab = clinicalWorkbenchBenefit('laboratorio');

    $source = clinicalWorkbenchPlan('ORIGEN');
    clinicalWorkbenchSetting($source, $tele, ['applies_clinically' => true, 'channel' => 'TYPE_1', 'telemedicine_service_list_id' => $macro, 'quota_scope' => 'UNLIMITED']);
    clinicalWorkbenchSetting($source, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_AFFILIATION_YEAR', 'quota' => 4]);
    clinicalWorkbenchSetting($source, $funeral, ['applies_clinically' => false]);
    clinicalWorkbenchSetting($source, $lab, ['applies_clinically' => true, 'channel' => 'LABORATORY', 'quota_scope' => 'DISTINCT_CASES', 'quota' => 2]);

    $target = clinicalWorkbenchPlan('DESTINO');
    clinicalWorkbenchSetting($target, $tele, ['applies_clinically' => true, 'channel' => null, 'quota_scope' => null]);
    clinicalWorkbenchSetting($target, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_CONTRACT', 'quota' => 9]);
    clinicalWorkbenchSetting($target, $funeral, ['applies_clinically' => false]);

    $rows = PlanClinicalUsageWorkbench::hydrate($target);

    $preview = PlanClinicalUsageWorkbench::previewCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_FILL_PENDING);
    expect($preview['apply'])->toHaveCount(1)
        ->and($preview['apply'][0]['label'])->toBe($tele->description)
        ->and($preview['apply'][0]['from'])->toBe('Pendiente')
        ->and($preview['keep'])->toHaveCount(2)
        ->and(array_keys($preview['extra']))->toBe([(int) $lab->id]);

    $result = PlanClinicalUsageWorkbench::applyCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_FILL_PENDING, [(string) $lab->id, 999999999]);

    $teleRow = clinicalWorkbenchRowFor($result['rows'], (int) $tele->id);
    $medsRow = clinicalWorkbenchRowFor($result['rows'], (int) $meds->id);
    $labRow = clinicalWorkbenchRowFor($result['rows'], (int) $lab->id);

    expect($teleRow['channel'])->toBe('TYPE_1')
        ->and($teleRow['telemedicine_service_list_id'])->toBe($macro)
        ->and($teleRow['copied_from'])->toBe($source->description)
        ->and($medsRow['quota_scope'])->toBe('PER_CONTRACT')
        ->and($medsRow['quota'])->toBe(9)
        ->and($medsRow['copied_from'])->toBeNull()
        ->and($labRow['origin'])->toBe(PlanClinicalUsageWorkbench::ORIGIN_LINK)
        ->and($labRow['quota'])->toBe(2)
        ->and($result['applied'])->toBe([$tele->description])
        ->and($result['added'])->toBe([$lab->description])
        ->and($result['rows'])->toHaveCount(4);

    // La copia no escribe nada en la base.
    expect(BenefitPlan::query()->where('plan_id', $target->id)->count())->toBe(3)
        ->and(PlanBenefitClinicalSetting::query()->where('plan_id', $target->id)->where('benefit_id', $tele->id)->value('channel'))->toBeNull();
});

it('al reemplazar, iguala al origen lo que ya estaba listo', function (): void {
    $meds = clinicalWorkbenchBenefit('medicamentos');

    $source = clinicalWorkbenchPlan('ORIGEN');
    clinicalWorkbenchSetting($source, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_AFFILIATION_YEAR', 'quota' => 4]);

    $target = clinicalWorkbenchPlan('DESTINO');
    clinicalWorkbenchSetting($target, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_CONTRACT', 'quota' => 9]);

    $rows = PlanClinicalUsageWorkbench::hydrate($target);
    $preview = PlanClinicalUsageWorkbench::previewCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_OVERWRITE);

    expect($preview['apply'])->toHaveCount(1)
        ->and($preview['apply'][0]['was_complete'])->toBeTrue();

    $result = PlanClinicalUsageWorkbench::applyCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_OVERWRITE);
    $row = clinicalWorkbenchRowFor($result['rows'], (int) $meds->id);

    expect($row['quota_scope'])->toBe('PER_AFFILIATION_YEAR')
        ->and($row['quota'])->toBe(4);
});

it('solo ofrece como origen planes completos y nunca el propio plan', function (): void {
    $meds = clinicalWorkbenchBenefit('medicamentos');

    $complete = clinicalWorkbenchPlan('COMPLETO');
    clinicalWorkbenchSetting($complete, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED']);

    $incomplete = clinicalWorkbenchPlan('INCOMPLETO');
    clinicalWorkbenchSetting($incomplete, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => null]);

    $options = PlanClinicalUsageWorkbench::copySourceOptions($complete);
    expect($options)->not->toHaveKey((int) $complete->id)
        ->and($options)->not->toHaveKey((int) $incomplete->id);

    $fromIncomplete = PlanClinicalUsageWorkbench::copySourceOptions($incomplete);
    expect($fromIncomplete)->toHaveKey((int) $complete->id)
        ->and($fromIncomplete[(int) $complete->id])->toContain('1 beneficios');
});

it('guarda en una transacción: vincula, crea en catálogo, persiste el mapa y deja traza con antes y después', function (): void {
    $macro = clinicalWorkbenchMacroId();
    $tele = clinicalWorkbenchBenefit('telemedicina');
    $catalog = clinicalWorkbenchBenefit('laboratorio');

    $plan = clinicalWorkbenchPlan('COBERTURAS', PlanPricingMode::Coberturas);
    $coverageA = Coverage::query()->create(['plan_id' => $plan->id, 'price' => 1000, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $coverageB = Coverage::query()->create(['plan_id' => $plan->id, 'price' => 3000, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    clinicalWorkbenchSetting($plan, $tele, ['applies_clinically' => true, 'channel' => null, 'quota_scope' => null]);

    $fingerprint = PlanClinicalUsageWorkbench::fingerprint($plan);
    $rows = PlanClinicalUsageWorkbench::hydrate($plan);
    foreach ($rows as $key => $row) {
        $rows[$key] = array_merge($row, ['channel' => 'TYPE_1', 'telemedicine_service_list_id' => $macro, 'quota_scope' => 'PER_AFFILIATION_YEAR', 'quota' => 6]);
    }

    $rows['nuevo-catalogo'] = array_merge(PlanClinicalUsageWorkbench::rowForCatalogBenefit($catalog), [
        'channel' => 'LABORATORY',
        'quota_scope' => 'DISTINCT_CASES',
        'quota' => 2,
    ]);

    $newName = mb_strtoupper('ORIENTACION NUTRICIONAL PEST '.uniqid());
    $rows['nuevo-beneficio'] = array_merge(PlanClinicalUsageWorkbench::rowForNewBenefit('  '.mb_strtolower($newName).'  '), [
        'applies_clinically' => false,
    ]);

    $result = PlanClinicalUsageWorkbench::save($plan, $rows, $fingerprint, clinicalWorkbenchActor());

    $created = Benefit::query()->where('description', $newName)->first();

    expect($created)->not->toBeNull()
        ->and($created->code)->toStartWith('TDEC-BN-')
        ->and($created->status)->toBe('ACTIVO')
        ->and($result['linked'])->toHaveCount(2)
        ->and($result['created'])->toBe([$newName])
        ->and(BenefitPlan::query()->where('plan_id', $plan->id)->pluck('benefit_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->toBe(collect([(int) $tele->id, (int) $catalog->id, (int) $created->id])->sort()->values()->all());

    // Plan por coberturas: el beneficio vinculado queda sin costo límite en cada cobertura.
    $limits = BenefitCoverage::query()->where('plan_id', $plan->id)->where('benefit_id', $catalog->id)->get();
    expect($limits)->toHaveCount(2)
        ->and($limits->pluck('limit')->filter()->all())->toBe([])
        ->and($limits->pluck('coverage_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())->toBe([(int) $coverageA->id, (int) $coverageB->id]);

    $teleSetting = PlanBenefitClinicalSetting::query()->where('plan_id', $plan->id)->where('benefit_id', $tele->id)->first();
    expect($teleSetting->channel)->toBe(ClinicalServiceChannel::Type1)
        ->and($teleSetting->quota_scope)->toBe(ClinicalQuotaScope::PerAffiliationYear)
        ->and($teleSetting->quota)->toBe(6)
        ->and($teleSetting->updated_by)->toBe('Analista Pest')
        ->and(PlanClinicalCompleteness::isComplete($plan))->toBeTrue();

    // El beneficio nuevo deja su configuración como valor por defecto del catálogo.
    expect($created->clinicalSetting?->applies_clinically)->toBeFalse();

    $events = PlanClinicalUsageEvent::query()->where('plan_id', $plan->id)->pluck('event')->all();
    expect($events)->toContain(PlanClinicalUsageTrace::MAP_SAVED)
        ->toContain(PlanClinicalUsageTrace::BENEFIT_LINKED)
        ->toContain(PlanClinicalUsageTrace::BENEFIT_CREATED);

    $saved = PlanClinicalUsageEvent::query()->where('plan_id', $plan->id)->where('event', PlanClinicalUsageTrace::MAP_SAVED)->firstOrFail();
    $teleChange = collect($saved->changes['benefits'])->firstWhere('benefit_id', (int) $tele->id);

    expect($saved->actor_name)->toBe('Analista Pest')
        ->and($saved->changes['complete'])->toBeTrue()
        ->and($teleChange['before'])->toContain('Sin servicio')
        ->and($teleChange['after'])->toContain('Macro')
        ->and($teleChange['after'])->toContain('6');
});

it('rechaza el guardado si otro usuario guardó en medio y no escribe nada', function (): void {
    $meds = clinicalWorkbenchBenefit('medicamentos');
    $plan = clinicalWorkbenchPlan('CONFLICTO');
    clinicalWorkbenchSetting($plan, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED']);

    $fingerprint = PlanClinicalUsageWorkbench::fingerprint($plan);
    $rows = PlanClinicalUsageWorkbench::hydrate($plan);

    // Otro analista guarda primero.
    PlanBenefitClinicalSetting::query()->where('plan_id', $plan->id)->update(['quota_scope' => 'PER_CONTRACT', 'quota' => 2]);

    foreach ($rows as $key => $row) {
        $rows[$key]['quota_scope'] = 'PER_AFFILIATION_YEAR';
        $rows[$key]['quota'] = 7;
    }

    expect(fn () => PlanClinicalUsageWorkbench::save($plan, $rows, $fingerprint, clinicalWorkbenchActor()))
        ->toThrow(PlanClinicalUsageConflictException::class);

    expect(PlanBenefitClinicalSetting::query()->where('plan_id', $plan->id)->value('quota'))->toBe(2)
        ->and(PlanClinicalUsageEvent::query()->where('plan_id', $plan->id)->where('event', PlanClinicalUsageTrace::MAP_SAVED)->exists())->toBeFalse();
});

it('no deja nada a medias si una fila es inválida', function (string $case): void {
    $meds = clinicalWorkbenchBenefit('medicamentos');
    $other = clinicalWorkbenchBenefit('otro');
    $plan = clinicalWorkbenchPlan('ATOMICO');
    clinicalWorkbenchSetting($plan, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED']);
    clinicalWorkbenchSetting($plan, $other, ['applies_clinically' => false]);

    $fingerprint = PlanClinicalUsageWorkbench::fingerprint($plan);
    $rows = PlanClinicalUsageWorkbench::hydrate($plan);
    $newName = mb_strtoupper('BENEFICIO HUERFANO '.uniqid());
    $rows['nuevo'] = PlanClinicalUsageWorkbench::rowForNewBenefit($newName);

    match ($case) {
        'duplicado' => $rows['dup'] = clinicalWorkbenchRowFor($rows, (int) $meds->id),
        'falta un beneficio del plan' => $rows = array_filter($rows, fn (array $row): bool => (int) ($row['benefit_id'] ?? 0) !== (int) $other->id),
        'beneficio nuevo sin nombre' => $rows['sin-nombre'] = PlanClinicalUsageWorkbench::rowForNewBenefit('   '),
    };

    try {
        PlanClinicalUsageWorkbench::save($plan, $rows, $fingerprint, clinicalWorkbenchActor());
        $thrown = false;
    } catch (InvalidArgumentException) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue()
        ->and(Benefit::query()->where('description', $newName)->exists())->toBeFalse()
        ->and(BenefitPlan::query()->where('plan_id', $plan->id)->count())->toBe(2);
})->with(['duplicado', 'falta un beneficio del plan', 'beneficio nuevo sin nombre']);

it('crea un servicio macro una sola vez aunque se repita el nombre con otra forma', function (): void {
    $plan = clinicalWorkbenchPlan('MACRO');
    $name = 'Nutrición a domicilio pest '.uniqid();

    $first = PlanClinicalUsageWorkbench::createServiceMacro('  '.$name.'  ', null, clinicalWorkbenchActor(), (int) $plan->id);
    $second = PlanClinicalUsageWorkbench::createServiceMacro(mb_strtoupper($name), 'otra', clinicalWorkbenchActor(), (int) $plan->id);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and((int) $second['service']->id)->toBe((int) $first['service']->id)
        ->and($first['service']->name)->toBe(mb_strtoupper($name))
        ->and((string) $first['service']->level)->toBe('1')
        ->and(PlanClinicalUsageWorkbench::serviceMacroNames())->toHaveKey((int) $first['service']->id)
        ->and(PlanClinicalUsageEvent::query()->where('plan_id', $plan->id)->where('event', PlanClinicalUsageTrace::SERVICE_MACRO_CREATED)->count())->toBe(1);

    expect(fn () => PlanClinicalUsageWorkbench::createServiceMacro('   ', null, clinicalWorkbenchActor()))
        ->toThrow(InvalidArgumentException::class);
});

it('la pantalla usa la mesa de trabajo, la tabla compacta y conserva el candado otp', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Pages/ManagePlanClinicalUsage.php');

    expect($page)
        ->toContain('PlanClinicalUsageWorkbench::save(')
        ->toContain("Action::make('copyFromPlan')")
        ->toContain("Action::make('addBenefit')")
        ->toContain("Action::make('history')")
        ->toContain('->table(PlanClinicalUsageRowSchema::columns())')
        ->toContain('#[Locked]')
        ->toContain('$this->assertClinicalUsageUnlocked();')
        ->not->toContain('->addable(true)');
});

function clinicalWorkbenchSuperAdmin(): User
{
    return User::factory()->make([
        'id' => 987655,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin-pest-'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]);
}

it('la página copia de otro plan, agrega un beneficio nuevo y guarda todo junto', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $macro = clinicalWorkbenchMacroId();
    $tele = clinicalWorkbenchBenefit('telemedicina');
    $meds = clinicalWorkbenchBenefit('medicamentos');

    $source = clinicalWorkbenchPlan('ORIGEN PAGINA');
    clinicalWorkbenchSetting($source, $tele, ['applies_clinically' => true, 'channel' => 'TYPE_1', 'telemedicine_service_list_id' => $macro, 'quota_scope' => 'UNLIMITED']);
    clinicalWorkbenchSetting($source, $meds, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'PER_AFFILIATION_YEAR', 'quota' => 4]);

    $target = clinicalWorkbenchPlan('DESTINO PAGINA');
    BenefitPlan::query()->create(['plan_id' => $target->id, 'benefit_id' => $tele->id]);

    $newName = mb_strtoupper('NUTRICION PEST '.uniqid());

    $component = Livewire\Livewire::actingAs(clinicalWorkbenchSuperAdmin())
        ->test(App\Filament\Business\Resources\Plans\Pages\ManagePlanClinicalUsage::class, ['record' => $target->id])
        ->assertSuccessful()
        ->assertSee($tele->description)
        ->assertSee('Elija el servicio clínico')
        ->assertSee('0 de 1 beneficios listos');

    $component
        ->mountAction('copyFromPlan')
        ->set('mountedActions.0.data.source_plan_id', $source->id)
        ->assertMountedActionModalSee('se copian')
        ->set('mountedActions.0.data.extra_benefit_ids', [(string) $meds->id])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertSet('hasUnsavedWork', true)
        ->assertSet('copiedFromPlanId', (int) $source->id)
        ->assertSee('Copiado de '.$source->description)
        ->assertSee('Se vincula al guardar');

    $component
        ->mountAction('addBenefit')
        ->set('mountedActions.0.data.source', 'new')
        ->set('mountedActions.0.data.new_description', mb_strtolower($newName))
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertSee($newName);

    $rows = $component->get('data.clinical_rows');
    $newKey = collect($rows)->search(fn (array $row): bool => ($row['origin'] ?? null) === PlanClinicalUsageWorkbench::ORIGIN_CREATE);
    $component->set("data.clinical_rows.{$newKey}.applies_clinically", false);

    $component->call('save')->assertHasNoErrors();

    $created = Benefit::query()->where('description', $newName)->firstOrFail();

    expect(BenefitPlan::query()->where('plan_id', $target->id)->pluck('benefit_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->toBe(collect([(int) $tele->id, (int) $meds->id, (int) $created->id])->sort()->values()->all())
        ->and(PlanClinicalCompleteness::isComplete($target))->toBeTrue();

    $saved = PlanClinicalUsageEvent::query()->where('plan_id', $target->id)->where('event', PlanClinicalUsageTrace::MAP_SAVED)->firstOrFail();
    expect((int) $saved->source_plan_id)->toBe((int) $source->id)
        ->and(PlanClinicalUsageEvent::query()->where('plan_id', $target->id)->where('event', PlanClinicalUsageTrace::COPY_APPLIED)->exists())->toBeTrue();

    $component->assertSet('hasUnsavedWork', false)->assertSet('copiedFromPlanId', null);
});

it('la página no acepta un beneficio nuevo que ya existe en el catálogo', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $existing = clinicalWorkbenchBenefit('existente');
    $target = clinicalWorkbenchPlan('DUPLICADO PAGINA');

    Livewire\Livewire::actingAs(clinicalWorkbenchSuperAdmin())
        ->test(App\Filament\Business\Resources\Plans\Pages\ManagePlanClinicalUsage::class, ['record' => $target->id])
        ->mountAction('addBenefit')
        ->set('mountedActions.0.data.source', 'new')
        ->set('mountedActions.0.data.new_description', mb_strtolower((string) $existing->description))
        ->callMountedAction()
        ->assertHasActionErrors(['new_description'])
        ->assertSet('hasUnsavedWork', false);
});

it('sin la clave otp la página no ofrece copiar ni agregar y no guarda', function (): void {
    $page = new App\Filament\Business\Resources\Plans\Pages\ManagePlanClinicalUsage;

    expect($page->clinicalUsageIsUnlocked())->toBeFalse();

    $actions = (fn (): array => $this->getHeaderActions())->call($page);
    $visible = array_values(array_filter($actions, fn (Filament\Actions\Action|Filament\Actions\ActionGroup $action): bool => $action->isVisible()));

    expect($visible)->toHaveCount(1)
        ->and($visible[0])->toBeInstanceOf(Filament\Actions\Action::class)
        ->and($visible[0]->getName())->toBe('unlockClinicalUsage');

    $group = collect($actions)->first(fn ($action): bool => $action instanceof Filament\Actions\ActionGroup);
    foreach ($group->getActions() as $inner) {
        expect($inner->isVisible())->toBeFalse();
    }

    expect(fn () => $page->save())->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('empareja por nombre la misma prestación cargada con otro id del catálogo y no la ofrece para duplicarla', function (): void {
    $suffix = mb_strtoupper(uniqid());
    $labSource = Benefit::query()->create(['description' => 'LABORATORIOS CON FINES DIAGNÓSTICOS '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $labTarget = Benefit::query()->create(['description' => 'LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $imgSource = Benefit::query()->create(['description' => 'IMAGENOLOGíA CON FINES DIAGNÓSTICOS '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $imgTarget = Benefit::query()->create(['description' => 'IMAGENOLOGÍA A DOMICILIO CON FINES DIAGNÓSTICOS '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);

    $source = clinicalWorkbenchPlan('ORIGEN CATALOGO DUPLICADO');
    clinicalWorkbenchSetting($source, $labSource, ['applies_clinically' => true, 'channel' => 'LABORATORY', 'quota_scope' => 'DISTINCT_CASES', 'quota' => 2]);
    clinicalWorkbenchSetting($source, $imgSource, ['applies_clinically' => true, 'channel' => 'IMAGING', 'quota_scope' => 'DISTINCT_CASES', 'quota' => 2]);
    // En el plan de origen el beneficio se llama distinto que en su catálogo (como el PLAN ESPECIAL real).
    BenefitPlan::query()->where('plan_id', $source->id)->where('benefit_id', $labSource->id)->update(['description' => $labTarget->description]);
    BenefitPlan::query()->where('plan_id', $source->id)->where('benefit_id', $imgSource->id)->update(['description' => 'IMAGENOLOGíA A DOMICILIO CON FINES DIAGNÓSTICOS '.$suffix]);

    $target = clinicalWorkbenchPlan('DESTINO CATALOGO DUPLICADO');
    BenefitPlan::query()->create(['plan_id' => $target->id, 'benefit_id' => $labTarget->id]);
    BenefitPlan::query()->create(['plan_id' => $target->id, 'benefit_id' => $imgTarget->id]);

    $rows = PlanClinicalUsageWorkbench::hydrate($target);
    $preview = PlanClinicalUsageWorkbench::previewCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_FILL_PENDING);

    expect($preview['apply'])->toHaveCount(2)
        ->and($preview['by_name'])->toBe(2)
        ->and($preview['extra'])->toBe([])
        ->and($preview['keep'])->toBe([]);

    $result = PlanClinicalUsageWorkbench::applyCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_FILL_PENDING, [$labSource->id, $imgSource->id]);

    // Se configura el beneficio propio del destino; el del origen nunca entra.
    expect(collect($result['rows'])->pluck('benefit_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->toBe(collect([(int) $labTarget->id, (int) $imgTarget->id])->sort()->values()->all())
        ->and($result['added'])->toBe([])
        ->and($result['matched_by_name'])->toHaveCount(2)
        ->and(clinicalWorkbenchRowFor($result['rows'], (int) $labTarget->id)['channel'])->toBe('LABORATORY')
        ->and(clinicalWorkbenchRowFor($result['rows'], (int) $imgTarget->id)['channel'])->toBe('IMAGING');

    // El catálogo no ofrece agregar la prestación equivalente.
    expect(PlanClinicalUsageWorkbench::catalogBenefitOptions($rows))
        ->not->toHaveKey((int) $labSource->id)
        ->not->toHaveKey((int) $imgSource->id);

    PlanClinicalUsageWorkbench::save($target, $result['rows'], PlanClinicalUsageWorkbench::fingerprint($target), clinicalWorkbenchActor());

    expect(BenefitPlan::query()->where('plan_id', $target->id)->count())->toBe(2)
        ->and(PlanClinicalCompleteness::isComplete($target))->toBeTrue();
});

it('no empareja cuando el nombre es ambiguo y lo deja para configurar a mano', function (): void {
    $suffix = mb_strtoupper(uniqid());
    $first = Benefit::query()->create(['description' => 'CONSULTA GENERAL '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $second = Benefit::query()->create(['description' => 'CONSULTA GENERAL '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $mine = Benefit::query()->create(['description' => 'Consulta  General '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);

    $source = clinicalWorkbenchPlan('ORIGEN AMBIGUO');
    clinicalWorkbenchSetting($source, $first, ['applies_clinically' => true, 'channel' => 'SPECIALIST', 'quota_scope' => 'UNLIMITED']);
    clinicalWorkbenchSetting($source, $second, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED']);

    $target = clinicalWorkbenchPlan('DESTINO AMBIGUO');
    BenefitPlan::query()->create(['plan_id' => $target->id, 'benefit_id' => $mine->id]);

    $rows = PlanClinicalUsageWorkbench::hydrate($target);
    $preview = PlanClinicalUsageWorkbench::previewCopy($rows, $source, PlanClinicalUsageWorkbench::COPY_OVERWRITE);

    expect($preview['apply'])->toBe([])
        ->and($preview['extra'])->toBe([])
        ->and($preview['keep'][0]['reason'])->toContain('más de un beneficio parecido');
});

it('al guardar rechaza vincular una prestación equivalente a otra que el plan ya tiene', function (): void {
    $suffix = mb_strtoupper(uniqid());
    $existing = Benefit::query()->create(['description' => 'URGENCIAS MENORES EN DOMICILIO '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);
    $twin = Benefit::query()->create(['description' => 'Urgencias menores en domicilio '.$suffix, 'status' => 'ACTIVO', 'created_by' => 'pest']);

    $plan = clinicalWorkbenchPlan('DUPLICADO AL GUARDAR');
    clinicalWorkbenchSetting($plan, $existing, ['applies_clinically' => true, 'channel' => 'MEDICATION', 'quota_scope' => 'UNLIMITED']);

    $fingerprint = PlanClinicalUsageWorkbench::fingerprint($plan);
    $rows = PlanClinicalUsageWorkbench::hydrate($plan);
    $rows['gemelo'] = PlanClinicalUsageWorkbench::rowForCatalogBenefit($twin);

    expect(fn () => PlanClinicalUsageWorkbench::save($plan, $rows, $fingerprint, clinicalWorkbenchActor()))
        ->toThrow(InvalidArgumentException::class, 'el mismo beneficio dos veces');

    expect(BenefitPlan::query()->where('plan_id', $plan->id)->count())->toBe(1);
});

it('tras confirmar la otp el menú de acciones aparece en la misma petición', function (): void {
    $page = new App\Filament\Business\Resources\Plans\Pages\ManagePlanClinicalUsage;

    // La petición empieza bloqueada: así arma Filament el encabezado.
    $actions = (fn (): array => $this->getHeaderActions())->call($page);
    $group = collect($actions)->first(fn ($action): bool => $action instanceof Filament\Actions\ActionGroup);
    $unlock = collect($actions)->first(fn ($action): bool => $action instanceof Filament\Actions\Action && $action->getName() === 'unlockClinicalUsage');

    expect($group)->not->toBeNull()
        ->and($group->isVisible())->toBeFalse();

    // El analista confirma la OTP dentro de esa misma petición.
    (fn () => $this->clinicalUsageUnlocked = true)->call($page);

    expect($group->isVisible())->toBeTrue()
        ->and($unlock->isVisible())->toBeFalse()
        ->and(collect($group->getActions())->map(fn ($action): string => $action->getName())->all())
        ->toBe(['copyFromPlan', 'addBenefit', 'history']);
});
