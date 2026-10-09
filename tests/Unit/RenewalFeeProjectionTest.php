<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Renovations\RenewalFeeProjection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * La tabla de renovaciones debe mostrar la tarifa que se aplicaría al aceptar.
 * Antes, fuera del período de 30 días, la propuesta copiaba la tarifa vigente
 * (p. ej. US$ 100 promocionales) mientras que la renovación anticipada cobraba
 * la de la tabla de tarifas (US$ 160).
 */
function renewalProjectionPackagePlan(): Plan
{
    $plan = Plan::query()->create([
        'code' => 'PEST-RENEWAL-PROJECTION',
        'description' => 'PLAN PAQUETE PROYECCION RENOVACION',
        'business_unit_id' => 1,
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => PlanPricingMode::Paquete->value,
        'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
    ]);

    foreach ([['0 a 40', 0, 40, 160], ['41 a 99', 41, 99, 380]] as [$etiqueta, $desde, $hasta, $tarifa]) {
        $ageRange = AgeRange::query()->create([
            'plan_id' => $plan->id,
            'range' => $etiqueta,
            'age_init' => $desde,
            'age_end' => $hasta,
            'status' => 'ACTIVO',
            'created_by' => 'pest',
        ]);

        Fee::query()->create([
            'code' => 'PEST-RENEWAL-'.$ageRange->id,
            'plan_id' => $plan->id,
            'age_range_id' => $ageRange->id,
            'coverage_id' => null,
            'price' => $tarifa,
            'range' => $etiqueta,
            'status' => 'ACTIVO',
            'created_by' => 'pest',
        ]);
    }

    return $plan;
}

function renewalProjectionAffiliate(string $birthDate, float $currentFee = 100.0, ?int $ageRangeId = 77): Affiliate
{
    return new Affiliate([
        'relationship' => 'TITULAR',
        'birth_date' => $birthDate,
        'fee' => $currentFee,
        'age_range_id' => $ageRangeId,
    ]);
}

it('proyecta con la edad de hoy dentro del período y con la de la fecha de renovación fuera de él', function (): void {
    $today = Carbon::parse('2026-10-08 15:30');
    $renewal = Carbon::parse('2026-11-22');

    expect(RenewalFeeProjection::referenceDate(true, $today, $renewal)->toDateString())->toBe('2026-10-08')
        ->and(RenewalFeeProjection::referenceDate(false, $today, $renewal)->toDateString())->toBe('2026-11-22')
        ->and($today->format('H:i'))->toBe('15:30');
});

it('fuera del período muestra la tarifa del catálogo, no la vigente', function (): void {
    $plan = renewalProjectionPackagePlan();
    $projection = new RenewalFeeProjection(new AffiliationAffiliateFeeCalculator);

    $resultado = $projection->forAffiliate(
        new Affiliation(['plan_id' => $plan->id]),
        renewalProjectionAffiliate('10/03/1990'),
        Carbon::parse('2026-11-22'),
    );

    expect($resultado['annual_fee'])->toBe(160.0)
        ->and($resultado['priced'])->toBeTrue()
        ->and($resultado['age_range_id'])->not->toBe(77);
});

it('cobra el rango de edad que tendrá el afiliado en la fecha de renovación', function (): void {
    $plan = renewalProjectionPackagePlan();
    $projection = new RenewalFeeProjection(new AffiliationAffiliateFeeCalculator);
    $affiliation = new Affiliation(['plan_id' => $plan->id]);

    // Cumple 41 el 01/11/2026: hoy (08/10) tiene 40, al renovar (22/11) tendrá 41.
    $afiliado = renewalProjectionAffiliate('01/11/1985');

    $alRenovar = $projection->forAffiliate($affiliation, $afiliado, Carbon::parse('2026-11-22'));
    $hoy = $projection->forAffiliate($affiliation, $afiliado, Carbon::parse('2026-10-08'));

    expect($alRenovar['annual_fee'])->toBe(380.0)
        ->and($hoy['annual_fee'])->toBe(160.0);
});

it('conserva la tarifa vigente cuando la edad no cae en ningún rango del plan', function (): void {
    $plan = renewalProjectionPackagePlan();
    $projection = new RenewalFeeProjection(new AffiliationAffiliateFeeCalculator);

    $resultado = $projection->forAffiliate(
        new Affiliation(['plan_id' => $plan->id]),
        renewalProjectionAffiliate('01/01/1910', 245.5, 12),
        Carbon::parse('2026-11-22'),
    );

    expect($resultado)->toBe([
        'annual_fee' => 245.5,
        'age_range_id' => 12,
        'priced' => false,
    ]);
});

it('conserva la tarifa vigente en un plan con coberturas sin cobertura asignada', function (): void {
    $plan = Plan::query()->create([
        'code' => 'PEST-RENEWAL-COVERAGES',
        'description' => 'PLAN CON COBERTURAS SIN COBERTURA',
        'business_unit_id' => 1,
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
    ]);

    $projection = new RenewalFeeProjection(new AffiliationAffiliateFeeCalculator);
    $affiliation = new Affiliation(['plan_id' => $plan->id, 'coverage_id' => null]);

    expect($projection->canRecalculateFees($affiliation))->toBeFalse()
        ->and($projection->forAffiliate($affiliation, renewalProjectionAffiliate('10/03/1990', 90.0, null), Carbon::parse('2026-11-22')))
        ->toBe(['annual_fee' => 90.0, 'age_range_id' => null, 'priced' => false]);
});

it('el job tarifica siempre y usa la misma fecha de referencia que la renovación anticipada', function (): void {
    $job = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PrepareAffiliationRenovations.php');
    $service = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationRenovationsService.php');

    expect($job)
        ->toContain('RenewalFeeProjection::referenceDate($isInRenewalPeriod, $today, $renewalDate)')
        ->toContain('$feeProjection->forAffiliate($affiliationForFees, $affiliate, $feeReferenceDate)')
        ->toContain('} elseif ($canRecalculateFees && $isInRenewalPeriod) {')
        ->not->toContain('$canRecalculateFees = $isInRenewalPeriod');

    expect($service)
        ->toContain('? $renovation->date_renewal->copy()->startOfDay()')
        ->toContain(': Carbon::today()->startOfDay();');
});
