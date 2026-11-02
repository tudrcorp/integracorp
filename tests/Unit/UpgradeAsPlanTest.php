<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Enums\UpgradeNetworkVisibility;
use App\Filament\Business\Resources\Plans\Pages\EditPlan;
use App\Filament\Business\Resources\Upgrades\Pages\CreateUpgrade;
use App\Filament\Business\Resources\Upgrades\Pages\EditUpgrade;
use App\Filament\Shared\Quotes\QuoteUpgradesSection;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use App\Models\Upgrade;
use App\Models\User;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use App\Support\Upgrades\UpgradeNetworkAudience;
use App\Support\Upgrades\UpgradePlanProjection;
use App\Support\Upgrades\UpgradeStructureValidator;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Corre contra la base configurada dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Auth::setUser(User::factory()->make(['id' => 999_775, 'name' => 'Analista Pest', 'agent_id' => null, 'is_agency' => false, 'code_agency' => null]));
});

afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $overrides
 */
function planUpgrade(array $overrides = []): Upgrade
{
    return UpgradeCatalogPersistence::save(null, [
        'name' => 'PEST PLAN FUNERARIO',
        'status' => 'ACTIVO',
        'is_plan' => true,
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'pest asistencia funeraria'], ['name' => 'pest traslado']],
        'coverages' => [
            ['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30], ['age_from' => 41, 'age_to' => 70, 'price' => 60]]],
            ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 50], ['age_from' => 41, 'age_to' => 70, 'price' => 90]]],
        ],
        ...$overrides,
    ]);
}

function completeClinicalUsage(Plan $plan): void
{
    foreach (PlanClinicalCompleteness::assignedBenefitIds($plan) as $benefitId) {
        PlanBenefitClinicalSetting::query()->create([
            'plan_id' => $plan->id,
            'benefit_id' => $benefitId,
            'applies_clinically' => false,
        ]);
    }
}

it('crea un plan real con coberturas, rangos, tarifas y beneficios', function (): void {
    $plan = UpgradePlanProjection::planOf(planUpgrade());

    expect($plan)->not->toBeNull()
        ->and($plan->description)->toBe('PEST PLAN FUNERARIO')
        ->and($plan->type)->toBe('BASICO')
        ->and($plan->pricingMode())->toBe(PlanPricingMode::Coberturas)
        ->and($plan->usesStructureWizard())->toBeTrue()
        ->and(Coverage::query()->where('plan_id', $plan->id)->orderBy('price')->pluck('price')->map(fn ($p) => (float) $p)->all())->toBe([2000.0, 5000.0])
        ->and(AgeRange::query()->where('plan_id', $plan->id)->orderBy('age_init')->pluck('range')->all())->toBe(['0 a 40', '41 a 70'])
        ->and(Fee::query()->where('plan_id', $plan->id)->where('status', 'ACTIVO')->count())->toBe(4)
        ->and(BenefitPlan::query()->where('plan_id', $plan->id)->pluck('description')->sort()->values()->all())->toBe(['PEST ASISTENCIA FUNERARIA', 'PEST TRASLADO'])
        ->and(BenefitCoverage::query()->where('plan_id', $plan->id)->whereNotNull('limit')->count())->toBe(0)
        ->and(BenefitCoverage::query()->where('plan_id', $plan->id)->count())->toBe(4);

    $fee = Fee::query()->where('plan_id', $plan->id)
        ->where('coverage_id', Coverage::query()->where('plan_id', $plan->id)->where('price', 5000)->value('id'))
        ->where('age_range_id', AgeRange::query()->where('plan_id', $plan->id)->where('age_init', 41)->value('id'))
        ->sole();

    expect((float) $fee->price)->toBe(90.0);
});

it('una sola cobertura sin monto se vuelve PAQUETE', function (): void {
    $plan = UpgradePlanProjection::planOf(planUpgrade([
        'coverages' => [['amount' => null, 'ranges' => [['age_from' => 0, 'age_to' => 70, 'price' => 40]]]],
    ]));

    expect($plan->pricingMode())->toBe(PlanPricingMode::Paquete)
        ->and(Coverage::query()->where('plan_id', $plan->id)->count())->toBe(0)
        ->and(Fee::query()->where('plan_id', $plan->id)->whereNull('coverage_id')->count())->toBe(1);
});

it('reutiliza un beneficio que ya existe', function (): void {
    $benefit = Benefit::query()->create(['description' => 'PEST TRASLADO', 'status' => 'ACTIVO', 'created_by' => 'Pest']);
    $plan = UpgradePlanProjection::planOf(planUpgrade());

    expect(BenefitPlan::query()->where('plan_id', $plan->id)->pluck('benefit_id')->map(fn ($id) => (int) $id)->all())->toContain((int) $benefit->id)
        ->and(Benefit::query()->where('description', 'PEST TRASLADO')->count())->toBe(1);
});

it('sin uso clínico completo no se puede cotizar, y con él sí', function (): void {
    $upgrade = planUpgrade();
    $plan = UpgradePlanProjection::planOf($upgrade);

    expect($plan->status)->toBe('INACTIVO')
        ->and(UpgradePlanProjection::pendingLabels($plan))->toHaveCount(2);

    completeClinicalUsage($plan);

    expect($plan->refresh()->status)->toBe('ACTIVO');

    PlanBenefitClinicalSetting::query()->where('plan_id', $plan->id)->first()->delete();

    expect($plan->refresh()->status)->toBe('INACTIVO');
});

it('inactivar el upgrade inactiva el plan, y reactivarlo lo devuelve', function (): void {
    $upgrade = planUpgrade();
    $plan = UpgradePlanProjection::planOf($upgrade);
    completeClinicalUsage($plan);

    UpgradeCatalogPersistence::changeStatus($upgrade, Upgrade::STATUS_INACTIVE);
    expect($plan->refresh()->status)->toBe('INACTIVO');

    UpgradeCatalogPersistence::changeStatus($upgrade, Upgrade::STATUS_ACTIVE);
    expect($plan->refresh()->status)->toBe('ACTIVO');
});

it('desmarcar no borra nada y volver a marcar recupera el mismo plan', function (): void {
    $upgrade = planUpgrade();
    $plan = UpgradePlanProjection::planOf($upgrade);
    completeClinicalUsage($plan);
    $fees = Fee::query()->where('plan_id', $plan->id)->count();
    $state = UpgradeCatalogPersistence::formState($upgrade->refresh());

    $upgrade = UpgradeCatalogPersistence::save($upgrade, [...$state, 'is_plan' => false]);

    expect($plan->refresh()->status)->toBe('INACTIVO')
        ->and(Fee::query()->where('plan_id', $plan->id)->count())->toBe($fees);

    $state = UpgradeCatalogPersistence::formState($upgrade);
    $upgrade = UpgradeCatalogPersistence::save($upgrade, [...$state, 'is_plan' => true, 'plan_business_unit_id' => 1]);

    expect(UpgradePlanProjection::planOf($upgrade)->id)->toBe($plan->id)
        ->and($plan->refresh()->status)->toBe('ACTIVO')
        ->and(Plan::query()->where('source_upgrade_id', $upgrade->id)->count())->toBe(1);
});

it('un cambio de precio actualiza la misma tarifa, rango y cobertura', function (): void {
    $upgrade = planUpgrade();
    $plan = UpgradePlanProjection::planOf($upgrade);
    $feeIds = Fee::query()->where('plan_id', $plan->id)->orderBy('id')->pluck('id')->all();
    $rangeIds = AgeRange::query()->where('plan_id', $plan->id)->orderBy('id')->pluck('id')->all();

    $state = UpgradeCatalogPersistence::formState($upgrade->refresh());
    $coverageKey = array_key_first($state['coverages']);
    $rangeKey = array_key_first($state['coverages'][$coverageKey]['ranges']);
    $state['coverages'][$coverageKey]['ranges'][$rangeKey]['price'] = '35.00';

    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(Fee::query()->where('plan_id', $plan->id)->orderBy('id')->pluck('id')->all())->toBe($feeIds)
        ->and(AgeRange::query()->where('plan_id', $plan->id)->orderBy('id')->pluck('id')->all())->toBe($rangeIds)
        ->and(Fee::query()->where('plan_id', $plan->id)->pluck('price')->map(fn ($p) => (float) $p)->all())->toContain(35.0);
});

it('rechaza lo que no puede ser plan', function (array $overrides, string $message): void {
    $errors = UpgradeStructureValidator::errors([
        'name' => 'X',
        'is_plan' => true,
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'A']],
        'coverages' => [
            ['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30]]],
            ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 50]]],
        ],
        ...$overrides,
    ]);

    expect(implode(' ', $errors))->toContain($message);
})->with([
    'sin unidad' => [['plan_business_unit_id' => null], 'unidad de negocio'],
    'sin servicios' => [['services' => []], 'al menos un servicio'],
    'rangos distintos' => [['coverages' => [
        ['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30]]],
        ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 50, 'price' => 50]]],
    ]], 'mismos rangos'],
    'cobertura sin monto entre varias' => [['coverages' => [
        ['amount' => null, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30]]],
        ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 50]]],
    ]], 'todas deben tener monto'],
]);

it('sin marcar el check no crea plan', function (): void {
    $upgrade = planUpgrade(['is_plan' => false]);

    expect(UpgradePlanProjection::planOf($upgrade))->toBeNull();
});

it('un nombre repetido no deja nada a medias', function (): void {
    Plan::query()->create(['code' => 'PEST-DUP', 'description' => 'PEST PLAN FUNERARIO', 'business_unit_id' => 1, 'type' => 'BASICO', 'status' => 'ACTIVO', 'created_by' => 'Pest']);
    $before = Upgrade::query()->count();

    expect(fn () => planUpgrade())->toThrow(InvalidArgumentException::class, 'Ya existe un plan');
    expect(Upgrade::query()->count())->toBe($before);
});

it('el plan hereda la visibilidad del upgrade en la red', function (): void {
    $upgrade = planUpgrade(['network_visibility' => UpgradeNetworkVisibility::BusinessOnly->value]);
    $plan = UpgradePlanProjection::planOf($upgrade);
    $agent = DB::table('agents')->value('id');
    $user = User::factory()->make(['id' => 999_776, 'agent_id' => $agent, 'is_agency' => false, 'code_agency' => null]);
    $options = collect([1 => 'INICIAL', $plan->id => 'PEST PLAN FUNERARIO', 'CM' => 'COTIZACIÓN MULTIPLE']);

    expect(UpgradeNetworkAudience::filterPlanOptions($options, $user)->keys()->all())->toBe([1, 'CM'])
        ->and(UpgradeNetworkAudience::filterPlanOptions($options->all(), $user))->toBe([1 => 'INICIAL', 'CM' => 'COTIZACIÓN MULTIPLE'])
        ->and(UpgradeNetworkAudience::rejectedPlanNames($user, [1, $plan->id]))->toBe(['PEST PLAN FUNERARIO']);

    $state = UpgradeCatalogPersistence::formState($upgrade->refresh());
    UpgradeCatalogPersistence::save($upgrade, [...$state, 'network_visibility' => UpgradeNetworkVisibility::AllNetwork->value]);
    \Illuminate\Support\Once::flush();

    expect(UpgradeNetworkAudience::filterPlanOptions($options, $user)->keys()->all())->toContain($plan->id)
        ->and(UpgradeNetworkAudience::rejectedPlanNames($user, [$plan->id]))->toBe([]);
});

it('la red lee la población del plan nuevo', function (): void {
    $plan = UpgradePlanProjection::planOf(planUpgrade());
    $range = AgeRange::query()->where('plan_id', $plan->id)->orderBy('age_init')->first();
    $form = [
        'plan' => (string) $plan->id,
        QuoteUpgradesSection::OTHER_PLAN_REPEATER => [['age_range_id' => $range->id, 'total_persons' => 4]],
    ];

    expect(QuoteUpgradesSection::isOtherPlan($plan->id))->toBeTrue()
        ->and(QuoteUpgradesSection::isOtherPlan(2))->toBeFalse()
        ->and(QuoteUpgradesSection::isOtherPlan('CM'))->toBeFalse()
        ->and(QuoteUpgradesSection::rateRows(fn (string $field): mixed => $form[$field] ?? null, network: true))
        ->toBe([['age_range_label' => '0 a 40', 'population' => 4]]);
});

it('la edición del plan enlazado lleva al upgrade', function (): void {
    $upgrade = planUpgrade();
    $plan = UpgradePlanProjection::planOf($upgrade);

    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make(['id' => 999_777, 'name' => 'Superadmin Pest', 'email' => 'superadmin.pest@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]));

    Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])->assertRedirect();
});

it('la lista de requisitos dice qué falta y qué sigue', function (): void {
    $empty = UpgradePlanProjection::checklist(['services' => [], 'coverages' => []]);

    expect(array_column($empty, 'state'))->toBe(['missing', 'missing', 'missing', 'later']);

    $different = UpgradePlanProjection::checklist([
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'A']],
        'coverages' => [
            ['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30]]],
            ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 50, 'price' => 50]]],
        ],
    ]);

    expect(array_column($different, 'state'))->toBe(['done', 'done', 'missing', 'later'])
        ->and($different[2]['detail'])->toContain('mismos rangos');

    $ready = UpgradePlanProjection::checklist([
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'A'], ['name' => 'B']],
        'coverages' => [['amount' => null, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30]]]],
    ]);

    expect(array_column($ready, 'state'))->toBe(['done', 'done', 'done', 'later'])
        ->and($ready[1]['detail'])->toContain('2 servicios');
});

it('el formulario del upgrade muestra los requisitos al marcar el check', function (): void {
    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make(['id' => 999_777, 'name' => 'Superadmin Pest', 'email' => 'superadmin.pest@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]));

    Livewire::test(CreateUpgrade::class)
        ->assertDontSee('Requisitos para crear el plan')
        ->set('data.is_plan', true)
        ->assertSuccessful()
        ->assertSee('Requisitos para crear el plan')
        ->assertSee('Después de guardar');

    $upgrade = planUpgrade();

    Livewire::test(EditUpgrade::class, ['record' => $upgrade->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Ir al uso clínico del plan')
        ->assertSee('Aún no se puede cotizar');
});
