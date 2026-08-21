<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * "Plan sin coberturas" era un número mágico: `plan_id === 1`. Eso dejaba a
 * cualquier otro paquete de beneficios sin tarifa posible, y ataba al plan 1 a
 * un único rango de edad (`age_range_id = 1`). Ahora es una propiedad del plan,
 * `plans.pricing_mode`.
 *
 * Los tests de lectura corren contra la base real; el que escribe abre una
 * transacción y la revierte.
 */
it('expone los dos modos con etiqueta en español', function (): void {
    expect(PlanPricingMode::options())->toBe([
        'COBERTURAS' => 'Plan con coberturas',
        'PAQUETE' => 'Paquete de beneficios',
    ])
        ->and(PlanPricingMode::Coberturas->usesCoverages())->toBeTrue()
        ->and(PlanPricingMode::Paquete->usesCoverages())->toBeFalse();
});

it('resuelve el modo desde el valor guardado y tolera minúsculas', function (): void {
    expect(PlanPricingMode::fromStored('PAQUETE'))->toBe(PlanPricingMode::Paquete)
        ->and(PlanPricingMode::fromStored('paquete'))->toBe(PlanPricingMode::Paquete)
        ->and(PlanPricingMode::fromStored(null))->toBeNull();
});

it('deja de tratar el plan inicial como el único plan sin coberturas', function (): void {
    $fuente = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationAffiliateFeeCalculator.php');

    expect($fuente)
        ->toContain('public function planHasNoCoverages(?int $planId): bool')
        // El filtro por rango fijo era lo que impedía más de un rango de edad.
        ->not->toContain("\$query->where('age_range_id', 1)");
});

it('sigue cotizando el plan inicial sin cobertura', function (): void {
    $plan = Plan::query()->find(AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID);

    if ($plan === null) {
        expect(true)->toBeTrue();

        return;
    }

    $calculator = new AffiliationAffiliateFeeCalculator;

    expect($calculator->planHasNoCoverages((int) $plan->id))->toBeTrue()
        ->and($calculator->resolveFeeForPlanCoverageAndAge((int) $plan->id, null, 40))->not->toBeNull();
});

it('sigue exigiendo cobertura en los planes que cobran por cobertura', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    foreach ([AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID, AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID] as $planId) {
        $plan = Plan::query()->find($planId);

        if ($plan === null) {
            continue;
        }

        expect($calculator->planHasNoCoverages($planId))->toBeFalse()
            ->and($calculator->resolveFeeForPlanCoverageAndAge($planId, null, 30))->toBeNull();
    }
});

it('no marca como paquete a un plan cuyas tarifas apuntan a una cobertura', function (): void {
    // Hay planes sin coberturas propias cuyas tarifas usan la cobertura de otro
    // plan (HESPERIA usa la del PLAN ESPECIAL). Marcarlos paquete los dejaría
    // sin tarifa, así que el backfill los tiene que haber dejado en COBERTURAS.
    $malClasificados = DB::table('plans')
        ->where('pricing_mode', PlanPricingMode::Paquete->value)
        ->whereExists(function ($query): void {
            $query->select(DB::raw(1))
                ->from('fees')
                ->whereColumn('fees.plan_id', 'plans.id')
                ->whereNotNull('fees.coverage_id');
        })
        ->count();

    expect($malClasificados)->toBe(0);
});

it('cotiza un paquete de beneficios con más de un rango de edad', function (): void {
    DB::beginTransaction();

    try {
        $plan = Plan::query()->create([
            'code' => 'PEST-PRICING-MODE',
            'description' => 'PLAN PAQUETE MULTIRANGO',
            'business_unit_id' => 1,
            'type' => 'BASICO',
            'status' => 'ACTIVO',
            'created_by' => 'pest',
            'pricing_mode' => PlanPricingMode::Paquete->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);

        $rangos = [
            ['0 a 40', 0, 40, 160],
            ['41 a 99', 41, 99, 380],
        ];

        foreach ($rangos as [$etiqueta, $desde, $hasta, $tarifa]) {
            $ageRange = App\Models\AgeRange::query()->create([
                'plan_id' => $plan->id,
                'range' => $etiqueta,
                'age_init' => $desde,
                'age_end' => $hasta,
                'status' => 'ACTIVO',
                'created_by' => 'pest',
            ]);

            App\Models\Fee::query()->create([
                'code' => 'PEST-'.$ageRange->id,
                'plan_id' => $plan->id,
                'age_range_id' => $ageRange->id,
                'coverage_id' => null,
                'price' => $tarifa,
                'range' => $etiqueta,
                'status' => 'ACTIVO',
                'created_by' => 'pest',
            ]);
        }

        $calculator = new AffiliationAffiliateFeeCalculator;

        expect((float) $calculator->resolveFeeForPlanCoverageAndAge((int) $plan->id, null, 20)?->price)->toBe(160.0)
            ->and((float) $calculator->resolveFeeForPlanCoverageAndAge((int) $plan->id, null, 70)?->price)->toBe(380.0);
    } finally {
        DB::rollBack();
    }
});
