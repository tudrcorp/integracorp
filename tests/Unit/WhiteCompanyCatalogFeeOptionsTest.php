<?php

declare(strict_types=1);

use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\WhiteCompanies\WhiteCompanyCatalogFeeOptions;

function catalogFeeForOptions(
    int $id,
    int $planId,
    string $planName,
    ?float $coverage,
    string $range,
    int $ageInit,
): Fee {
    $plan = new Plan(['description' => $planName]);
    $plan->id = $planId;

    $ageRange = new AgeRange([
        'plan_id' => $planId,
        'range' => $range,
        'age_init' => $ageInit,
    ]);
    $ageRange->setRelation('plan', $plan);

    $fee = new Fee([
        'coverage' => $coverage,
        'status' => 'ACTIVO',
    ]);
    $fee->id = $id;
    $fee->plan_id = $planId;
    $fee->setRelation('plan', $plan);
    $fee->setRelation('ageRange', $ageRange);
    $fee->setRelation(
        'coverageRecord',
        $coverage === null ? null : new Coverage(['price' => $coverage, 'plan_id' => $planId]),
    );

    return $fee;
}

it('ordena el catalogo por plan, cobertura y rango de edad, no por id', function (): void {
    $fees = [
        catalogFeeForOptions(4, 2, 'PLAN IDEAL', 3000, '0 a 45', 0),
        catalogFeeForOptions(62, 2, 'PLAN IDEAL', 2000, '0 a 45', 0),
        catalogFeeForOptions(1, 1, 'PLAN INICIAL', null, '0 a 99', 0),
        catalogFeeForOptions(2, 2, 'PLAN IDEAL', 1000, '0 a 45', 0),
        catalogFeeForOptions(7, 2, 'PLAN IDEAL', 1000, '46 a 75', 46),
        catalogFeeForOptions(19, 3, 'PLAN ESPECIAL', 5000, '0 A 30', 0),
    ];

    $labels = array_values(WhiteCompanyCatalogFeeOptions::labels($fees));

    expect($labels[0])->toContain('PLAN INICIAL')
        ->and($labels[1])->toBe('PLAN IDEAL · 1.000 UD$ · 0 a 45 años')
        ->and($labels[2])->toBe('PLAN IDEAL · 2.000 UD$ · 0 a 45 años')
        ->and($labels[3])->toBe('PLAN IDEAL · 3.000 UD$ · 0 a 45 años')
        ->and($labels[4])->toBe('PLAN IDEAL · 1.000 UD$ · 46 a 75 años')
        ->and($labels[5])->toContain('PLAN ESPECIAL');
});

it('encuentra la cobertura 2000 aunque se busque sin el punto de miles', function (): void {
    $fees = [
        catalogFeeForOptions(2, 2, 'PLAN IDEAL', 1000, '0 a 45', 0),
        catalogFeeForOptions(62, 2, 'PLAN IDEAL', 2000, '0 a 45', 0),
        catalogFeeForOptions(4, 2, 'PLAN IDEAL', 3000, '0 a 45', 0),
    ];
    $options = WhiteCompanyCatalogFeeOptions::labels($fees);

    $byDigits = WhiteCompanyCatalogFeeOptions::matching($options, '2000', $fees);
    $byFormatted = WhiteCompanyCatalogFeeOptions::matching($options, '2.000', $fees);
    $byPlan = WhiteCompanyCatalogFeeOptions::matching($options, 'ideal', $fees);

    expect($byDigits)->toHaveKey(62)
        ->and($byDigits)->not->toHaveKey(2)
        ->and($byFormatted)->toHaveKey(62)
        ->and($byPlan)->toHaveCount(3);
});

it('omite tarifas ya pactadas y usa el precio de la cobertura relacionada', function (): void {
    $fees = [
        catalogFeeForOptions(2, 2, 'PLAN IDEAL', 1000, '0 a 45', 0),
        catalogFeeForOptions(62, 2, 'PLAN IDEAL', 2000, '0 a 45', 0),
    ];

    $options = WhiteCompanyCatalogFeeOptions::labels($fees, [2]);

    expect($options)->not->toHaveKey(2)
        ->and($options)->toHaveKey(62)
        ->and($options[62])->toBe('PLAN IDEAL · 2.000 UD$ · 0 a 45 años');
});
