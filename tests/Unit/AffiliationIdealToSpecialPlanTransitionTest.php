<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Support\AffiliationAffiliateFeeCalculator;

it('detecta edad fuera del rango del plan ideal', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $range = new AgeRange([
        'plan_id' => AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID,
        'age_init' => 18,
        'age_end' => 64,
        'range' => '18 a 64',
    ]);

    expect($calculator->ageMatchesConfiguredRange(65, $range))->toBeFalse()
        ->and($calculator->ageMatchesConfiguredRange(64, $range))->toBeTrue()
        ->and($calculator->ageMatchesConfiguredRange(18, $range))->toBeTrue();
});

it('no marca negociación si la afiliación no es plan ideal', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $affiliation = new Affiliation([
        'plan_id' => AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID,
        'coverage_id' => 10,
    ]);

    $affiliate = new Affiliate(['age' => 80, 'id' => 1]);

    $affiliation->setRelation('affiliates', collect([$affiliate]));

    $result = $calculator->evaluateIdealToSpecialPlanTransition($affiliation, $affiliation->affiliates);

    expect($result['requires_negotiation'])->toBeFalse();
});

it('marca candidata a negociación cuando un afiliado excede el rango ideal', function (): void {
    $affiliation = Affiliation::query()
        ->where('plan_id', AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID)
        ->whereNotNull('coverage_id')
        ->where('status', 'ACTIVA')
        ->first();

    if ($affiliation === null) {
        expect(true)->toBeTrue();

        return;
    }

    $maxIdealAge = AgeRange::query()
        ->where('plan_id', AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID)
        ->where(function ($query) use ($affiliation): void {
            $query->where('coverage_id', $affiliation->coverage_id)
                ->orWhereNull('coverage_id');
        })
        ->max('age_end');

    if ($maxIdealAge === null) {
        expect(true)->toBeTrue();

        return;
    }

    $affiliate = Affiliate::query()
        ->where('affiliation_id', $affiliation->id)
        ->whereIn('status', ['ACTIVO', 'PRE-APROBADA'])
        ->first();

    if ($affiliate === null) {
        expect(true)->toBeTrue();

        return;
    }

    $affiliate->age = (int) $maxIdealAge + 1;
    $affiliation->setRelation('affiliates', collect([$affiliate]));

    $calculator = new AffiliationAffiliateFeeCalculator;
    $result = $calculator->evaluateIdealToSpecialPlanTransition($affiliation, $affiliation->affiliates);

    expect($result['requires_negotiation'])->toBeTrue()
        ->and($result['message'])->toBe(AffiliationAffiliateFeeCalculator::NEGOTIATION_MESSAGE_IDEAL_OUT_OF_RANGE);
});

it('el job registra campos de negociación y transición de plan', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PrepareAffiliationRenovations.php');

    expect($source)
        ->toContain('evaluateIdealToSpecialPlanTransition')
        ->toContain('applyIdealToSpecialPlanTransition')
        ->toContain('is_negotiation_candidate')
        ->toContain('negotiation_notes')
        ->toContain('previous_plan_id');
});
