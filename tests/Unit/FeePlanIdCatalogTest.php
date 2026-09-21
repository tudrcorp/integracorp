<?php

declare(strict_types=1);

use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\WhiteCompanies\WhiteCompanyCatalogFeeOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * `fees.plan_id` es la columna canónica que dice a qué plan pertenece una
 * tarifa. Antes el plan se deducía por `fees.age_range_id -> age_ranges.plan_id`
 * y, cuando el rango de edad no existía, la tarifa entraba en cualquier plan
 * que compartiera su cobertura y podía cobrarse el precio equivocado.
 *
 * Estos tests corren contra la base real (tests/Unit no usa RefreshDatabase),
 * así que son de solo lectura salvo los que abren una transacción y la revierten.
 */
it('expone plan como belongsTo sobre fees.plan_id y no a través de age_ranges', function (): void {
    $relation = (new Fee)->plan();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getForeignKeyName())->toBe('plan_id')
        ->and($relation->getRelated())->toBeInstanceOf(Plan::class);
});

it('no deja ninguna tarifa con un plan que contradiga su rango de edad', function (): void {
    $contradictorias = DB::table('fees')
        ->join('age_ranges', 'age_ranges.id', '=', 'fees.age_range_id')
        ->whereNotNull('fees.plan_id')
        ->whereColumn('fees.plan_id', '<>', 'age_ranges.plan_id')
        ->count();

    expect($contradictorias)->toBe(0);
});

it('aísla tarifas por plan cuando dos planes comparten la misma cobertura', function (): void {
    // El escenario que el esquema viejo no podía distinguir: una cobertura
    // vendida por más de un plan, donde el precio correcto depende del plan.
    $coverageId = DB::table('fees')
        ->whereNotNull('coverage_id')
        ->whereNotNull('plan_id')
        ->groupBy('coverage_id')
        ->havingRaw('COUNT(DISTINCT plan_id) > 1')
        ->value('coverage_id');

    if ($coverageId === null) {
        expect(true)->toBeTrue();

        return;
    }

    $calculator = new AffiliationAffiliateFeeCalculator;

    $fees = Fee::query()
        ->with('ageRange')
        ->where('coverage_id', $coverageId)
        ->whereNotNull('plan_id')
        ->get();

    $comprobadas = 0;

    foreach ($fees->groupBy('plan_id') as $planId => $feesDelPlan) {
        foreach ($feesDelPlan as $fee) {
            $edad = $fee->ageRange?->age_init;

            if (! filled($edad)) {
                continue;
            }

            $resuelta = $calculator->resolveFeeForPlanCoverageAndAge(
                (int) $planId,
                (int) $coverageId,
                (int) $edad,
            );

            expect($resuelta)->not->toBeNull()
                ->and((int) $resuelta->plan_id)->toBe((int) $planId);

            $comprobadas++;
        }
    }

    expect($comprobadas)->toBeGreaterThan(0);
});

it('nunca devuelve una tarifa de otro plan al resolver por cobertura y edad', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $fees = Fee::query()
        ->with('ageRange')
        ->whereNotNull('plan_id')
        ->whereNotNull('coverage_id')
        ->whereHas('ageRange', fn ($query) => $query->whereNotNull('age_init'))
        ->get();

    if ($fees->isEmpty()) {
        expect(true)->toBeTrue();

        return;
    }

    foreach ($fees as $fee) {
        $resuelta = $calculator->resolveFeeForPlanCoverageAndAge(
            (int) $fee->plan_id,
            (int) $fee->coverage_id,
            (int) $fee->ageRange->age_init,
        );

        if ($resuelta === null) {
            continue;
        }

        expect((int) $resuelta->plan_id)->toBe((int) $fee->plan_id);
    }
});

it('descarta del cálculo las tarifas que quedaron sin plan', function (): void {
    $huerfanas = Fee::query()
        ->with('ageRange')
        ->whereNull('plan_id')
        ->whereNotNull('coverage_id')
        ->get();

    if ($huerfanas->isEmpty()) {
        expect(true)->toBeTrue();

        return;
    }

    $calculator = new AffiliationAffiliateFeeCalculator;

    // Una tarifa sin plan no puede colarse en ningún plan que comparta su
    // cobertura, que es justo lo que hacía el fallback permisivo anterior.
    foreach ($huerfanas as $huerfana) {
        $planesDeLaCobertura = Fee::query()
            ->where('coverage_id', $huerfana->coverage_id)
            ->whereNotNull('plan_id')
            ->distinct()
            ->pluck('plan_id');

        foreach ($planesDeLaCobertura as $planId) {
            foreach ([0, 18, 40, 70, 99] as $edad) {
                $resuelta = $calculator->resolveFeeForPlanCoverageAndAge(
                    (int) $planId,
                    (int) $huerfana->coverage_id,
                    $edad,
                );

                expect($resuelta?->id)->not->toBe($huerfana->id);
            }
        }
    }
});

it('rechaza una tarifa sin plan_id sin importar el plan consultado', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $fee = new Fee;
    $fee->plan_id = null;

    expect($calculator->feeBelongsToPlan($fee, AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID))->toBeFalse()
        ->and($calculator->feeBelongsToPlan($fee, AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID))->toBeFalse()
        ->and($calculator->feeBelongsToPlan($fee, AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID))->toBeFalse();
});

it('acepta una tarifa solo para su propio plan', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $fee = new Fee;
    $fee->plan_id = AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID;

    expect($calculator->feeBelongsToPlan($fee, AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID))->toBeTrue()
        ->and($calculator->feeBelongsToPlan($fee, AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID))->toBeFalse();
});

it('sigue resolviendo el plan inicial por su rango de edad fijo', function (): void {
    $feeInicial = Fee::query()
        ->where('age_range_id', 1)
        ->where('plan_id', AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID)
        ->first();

    if ($feeInicial === null) {
        expect(true)->toBeTrue();

        return;
    }

    $calculator = new AffiliationAffiliateFeeCalculator;

    $resuelta = $calculator->resolveFeeForPlanCoverageAndAge(
        AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID,
        null,
        35,
        true,
    );

    expect($resuelta)->not->toBeNull()
        ->and($resuelta->id)->toBe($feeInicial->id);
});

it('completa plan_id desde el rango de edad al guardar una tarifa que no lo trae', function (): void {
    $ageRange = AgeRange::query()
        ->whereNotNull('plan_id')
        ->whereIn('plan_id', Plan::query()->select('id'))
        ->first();

    if ($ageRange === null) {
        expect(true)->toBeTrue();

        return;
    }

    DB::beginTransaction();

    try {
        $fee = new Fee;
        $fee->code = 'TEST-PLAN-ID-BACKFILL';
        $fee->age_range_id = $ageRange->id;
        $fee->coverage_id = null;
        $fee->price = 1;
        $fee->status = 'ACTIVO';
        $fee->created_by = 'test';
        $fee->save();

        expect((int) $fee->fresh()->plan_id)->toBe((int) $ageRange->plan_id);
    } finally {
        DB::rollBack();
    }
});

it('respeta el plan_id explícito en vez de derivarlo del rango de edad', function (): void {
    $ageRange = AgeRange::query()
        ->whereNotNull('plan_id')
        ->whereIn('plan_id', Plan::query()->select('id'))
        ->first();

    $otroPlan = Plan::query()
        ->where('id', '<>', $ageRange?->plan_id)
        ->first();

    if ($ageRange === null || $otroPlan === null) {
        expect(true)->toBeTrue();

        return;
    }

    DB::beginTransaction();

    try {
        $fee = new Fee;
        $fee->code = 'TEST-PLAN-ID-EXPLICITO';
        $fee->plan_id = $otroPlan->id;
        $fee->age_range_id = $ageRange->id;
        $fee->coverage_id = null;
        $fee->price = 1;
        $fee->status = 'ACTIVO';
        $fee->created_by = 'test';
        $fee->save();

        expect((int) $fee->fresh()->plan_id)->toBe((int) $otroPlan->id);
    } finally {
        DB::rollBack();
    }
});

it('filtra por plan en SQL en vez de descartar en PHP', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationAffiliateFeeCalculator.php');

    expect($source)->toContain('->forPlan($planId)')
        ->and($source)->not->toContain('$ageRange->plan_id === $planId');
});

it('etiqueta las tarifas del catálogo con la relación plan directa', function (): void {
    $fee = Fee::query()
        ->with(['plan', 'ageRange', 'coverageRecord'])
        ->whereNotNull('plan_id')
        ->whereHas('plan')
        ->first();

    if ($fee === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect(WhiteCompanyCatalogFeeOptions::label($fee))
        ->toContain((string) $fee->plan->description);
});
