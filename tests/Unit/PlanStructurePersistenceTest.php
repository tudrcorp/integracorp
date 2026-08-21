<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructurePersistence;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se
 * revierte, igual que FeePlanIdCatalogTest. No se deja nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function crearPlanDePrueba(PlanPricingMode $mode): Plan
{
    return Plan::query()->create([
        'code' => PlanCodeGenerator::next(),
        'description' => 'PLAN DE PRUEBA '.$mode->value,
        'business_unit_id' => 1,
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => $mode->value,
        'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
    ]);
}

function crearBeneficioDePrueba(string $descripcion): Benefit
{
    return Benefit::query()->create([
        'description' => $descripcion,
        'status' => 'ACTIVO',
        'created_by' => 'pest',
    ]);
}

it('guarda la matriz de costos límite por beneficio y cobertura', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);
    $naturales = crearBeneficioDePrueba('Accidentes naturales');
    $bano = crearBeneficioDePrueba('Accidente de baño');

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [
            'k1' => ['coverage_key' => 'k1', 'price' => 1000],
            'k2' => ['coverage_key' => 'k2', 'price' => 3000],
        ],
        'plan_benefits' => [
            'b1' => ['benefit_id' => $naturales->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 1000],
                ['coverage_key' => 'k2', 'limit' => 3000],
            ]],
            'b2' => ['benefit_id' => $bano->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 400],
                ['coverage_key' => 'k2', 'limit' => null],
            ]],
        ],
        'plan_age_ranges' => [],
    ]);

    $mil = Coverage::query()->where('plan_id', $plan->id)->where('price', 1000)->firstOrFail();
    $tresMil = Coverage::query()->where('plan_id', $plan->id)->where('price', 3000)->firstOrFail();

    $limite = fn (Benefit $b, Coverage $c) => BenefitCoverage::query()
        ->where('plan_id', $plan->id)
        ->where('benefit_id', $b->id)
        ->where('coverage_id', $c->id)
        ->value('limit');

    expect((float) $limite($naturales, $mil))->toBe(1000.0)
        ->and((float) $limite($naturales, $tresMil))->toBe(3000.0)
        ->and((float) $limite($bano, $mil))->toBe(400.0)
        ->and($limite($bano, $tresMil))->toBeNull();
});

it('guarda una tarifa por rango de edad y cobertura', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [
            'k1' => ['coverage_key' => 'k1', 'price' => 1000],
            'k2' => ['coverage_key' => 'k2', 'price' => 3000],
        ],
        'plan_benefits' => [],
        'plan_age_ranges' => [
            'r1' => ['range' => '1 a 23', 'age_init' => 1, 'age_end' => 23, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 120],
                ['coverage_key' => 'k2', 'rate' => 140],
            ]],
            'r2' => ['range' => '24 a 40', 'age_init' => 24, 'age_end' => 40, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 180],
                ['coverage_key' => 'k2', 'rate' => 200],
            ]],
        ],
    ]);

    expect(Fee::query()->where('plan_id', $plan->id)->count())->toBe(4);

    $calculator = new AffiliationAffiliateFeeCalculator;
    $mil = Coverage::query()->where('plan_id', $plan->id)->where('price', 1000)->firstOrFail();

    expect((float) $calculator->resolveFeeForPlanCoverageAndAge($plan->id, (int) $mil->id, 20)?->price)->toBe(120.0)
        ->and((float) $calculator->resolveFeeForPlanCoverageAndAge($plan->id, (int) $mil->id, 30)?->price)->toBe(180.0);
});

it('crea rangos de edad propios del plan y no reutiliza los del catálogo', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => ['k1' => ['coverage_key' => 'k1', 'price' => 1000]],
        'plan_benefits' => [],
        'plan_age_ranges' => [
            'r1' => ['range' => '1 a 23', 'age_init' => 1, 'age_end' => 23, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 120],
            ]],
        ],
    ]);

    $rangos = AgeRange::query()->where('plan_id', $plan->id)->get();

    expect($rangos)->toHaveCount(1)
        ->and((int) $rangos->first()->plan_id)->toBe((int) $plan->id)
        ->and((int) $rangos->first()->age_init)->toBe(1)
        ->and((int) $rangos->first()->age_end)->toBe(23);
});

it('guarda un paquete de beneficios con varios rangos y tarifa plana', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Paquete);
    $beneficios = collect(['Telemedicina', 'Orientación médica', 'Descuentos en farmacia'])
        ->map(fn (string $d): int => (int) crearBeneficioDePrueba($d)->id)
        ->all();

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Paquete->value,
        'package_benefit_ids' => $beneficios,
        'package_age_ranges' => [
            'r1' => ['range' => '0 a 40', 'age_init' => 0, 'age_end' => 40, 'rate' => 160],
            'r2' => ['range' => '41 a 65', 'age_init' => 41, 'age_end' => 65, 'rate' => 240],
            'r3' => ['range' => '66 a 99', 'age_init' => 66, 'age_end' => 99, 'rate' => 380],
        ],
    ]);

    expect(BenefitPlan::query()->where('plan_id', $plan->id)->count())->toBe(3)
        ->and(Coverage::query()->where('plan_id', $plan->id)->count())->toBe(0)
        ->and(BenefitCoverage::query()->where('plan_id', $plan->id)->count())->toBe(0)
        ->and(Fee::query()->where('plan_id', $plan->id)->whereNull('coverage_id')->count())->toBe(3);
});

it('cotiza un paquete de beneficios por edad, que antes era imposible fuera del plan 1', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Paquete);

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Paquete->value,
        'package_benefit_ids' => [(int) crearBeneficioDePrueba('Telemedicina')->id],
        'package_age_ranges' => [
            'r1' => ['range' => '0 a 40', 'age_init' => 0, 'age_end' => 40, 'rate' => 160],
            'r2' => ['range' => '41 a 99', 'age_init' => 41, 'age_end' => 99, 'rate' => 380],
        ],
    ]);

    $calculator = new AffiliationAffiliateFeeCalculator;

    expect((float) $calculator->resolveFeeForPlanCoverageAndAge($plan->id, null, 20)?->price)->toBe(160.0)
        ->and((float) $calculator->resolveFeeForPlanCoverageAndAge($plan->id, null, 70)?->price)->toBe(380.0);
});

it('al reeditar conserva los límites de las coberturas que siguen y descarta los de la eliminada', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);
    $bano = crearBeneficioDePrueba('Accidente de baño');

    $datos = [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [
            'k1' => ['coverage_key' => 'k1', 'price' => 1000],
            'k2' => ['coverage_key' => 'k2', 'price' => 3000],
        ],
        'plan_benefits' => [
            'b1' => ['benefit_id' => $bano->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 400],
                ['coverage_key' => 'k2', 'limit' => 2000],
            ]],
        ],
        'plan_age_ranges' => [
            'r1' => ['range' => '1 a 23', 'age_init' => 1, 'age_end' => 23, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 120],
                ['coverage_key' => 'k2', 'rate' => 140],
            ]],
        ],
    ];

    PlanStructurePersistence::persist($plan, $datos);

    $hidratado = PlanStructurePersistence::hydrate($plan->fresh());

    // El analista quita la cobertura de 3.000 y vuelve a guardar.
    $hidratado['plan_coverages'] = [$hidratado['plan_coverages'][0]];
    $hidratado['plan_benefits'][0]['limits'] = [$hidratado['plan_benefits'][0]['limits'][0]];
    $hidratado['plan_age_ranges'][0]['rates'] = [$hidratado['plan_age_ranges'][0]['rates'][0]];

    PlanStructurePersistence::persist($plan->fresh(), $hidratado);

    expect(Coverage::query()->where('plan_id', $plan->id)->count())->toBe(1)
        ->and(BenefitCoverage::query()->where('plan_id', $plan->id)->count())->toBe(1)
        ->and(Fee::query()->where('plan_id', $plan->id)->count())->toBe(1)
        ->and((float) BenefitCoverage::query()->where('plan_id', $plan->id)->value('limit'))->toBe(400.0);
});

it('desvincula la cobertura eliminada en vez de borrarla', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => ['k1' => ['coverage_key' => 'k1', 'price' => 7777]],
        'plan_benefits' => [],
        'plan_age_ranges' => [],
    ]);

    $coberturaId = (int) Coverage::query()->where('plan_id', $plan->id)->value('id');

    PlanStructurePersistence::persist($plan->fresh(), [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [],
        'plan_benefits' => [],
        'plan_age_ranges' => [],
    ]);

    $cobertura = Coverage::query()->find($coberturaId);

    expect($cobertura)->not->toBeNull()
        ->and($cobertura->plan_id)->toBeNull();
});

it('reconstruye el estado del asistente al hidratar un plan guardado', function (): void {
    $plan = crearPlanDePrueba(PlanPricingMode::Coberturas);
    $beneficio = crearBeneficioDePrueba('Accidente de baño');

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [
            'k1' => ['coverage_key' => 'k1', 'price' => 1000],
            'k2' => ['coverage_key' => 'k2', 'price' => 3000],
        ],
        'plan_benefits' => [
            'b1' => ['benefit_id' => $beneficio->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 400],
                ['coverage_key' => 'k2', 'limit' => null],
            ]],
        ],
        'plan_age_ranges' => [
            'r1' => ['range' => '1 a 23', 'age_init' => 1, 'age_end' => 23, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 120],
                ['coverage_key' => 'k2', 'rate' => 140],
            ]],
        ],
    ]);

    $hidratado = PlanStructurePersistence::hydrate($plan->fresh());

    expect($hidratado['pricing_mode'])->toBe(PlanPricingMode::Coberturas->value)
        ->and($hidratado['plan_coverages'])->toHaveCount(2)
        ->and($hidratado['plan_benefits'])->toHaveCount(1)
        ->and((float) $hidratado['plan_benefits'][0]['limits'][0]['limit'])->toBe(400.0)
        ->and($hidratado['plan_benefits'][0]['limits'][1]['limit'])->toBeNull()
        ->and($hidratado['plan_age_ranges'][0]['range'])->toBe('1 a 23')
        ->and((float) $hidratado['plan_age_ranges'][0]['rates'][1]['rate'])->toBe(140.0);
});
