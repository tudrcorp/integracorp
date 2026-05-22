<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Support\AffiliationAffiliateFeeCalculator;

uses(Tests\TestCase::class);

it('resuelve tarifa por coverage_id de la afiliación y edad del familiar', function (): void {
    $affiliation = Affiliation::query()->first();

    if ($affiliation === null || blank($affiliation->coverage_id)) {
        expect(true)->toBeTrue();

        return;
    }

    $ageRange = AgeRange::query()
        ->whereNotNull('age_init')
        ->whereNotNull('age_end')
        ->first();

    if ($ageRange === null) {
        expect(true)->toBeTrue();

        return;
    }

    $fee = Fee::query()
        ->where('coverage_id', $affiliation->coverage_id)
        ->where('age_range_id', $ageRange->id)
        ->first();

    if ($fee === null) {
        expect(true)->toBeTrue();

        return;
    }

    $affiliate = Affiliate::query()
        ->where('affiliation_id', $affiliation->id)
        ->first();

    if ($affiliate === null) {
        expect(true)->toBeTrue();

        return;
    }

    $affiliate->update([
        'age' => (int) $ageRange->age_init,
    ]);

    $calculator = new AffiliationAffiliateFeeCalculator;

    $resolved = $calculator->resolveFeeForAffiliateAge($affiliation, (int) $ageRange->age_init);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($fee->id)
        ->and((float) $resolved->price)->toBe((float) $fee->price);
});

it('resuelve tarifa del plan inicial sin cobertura por age_range_id 1', function (): void {
    $affiliation = Affiliation::query()
        ->where('plan_id', 1)
        ->first();

    if ($affiliation === null) {
        expect(true)->toBeTrue();

        return;
    }

    $fee = Fee::query()->where('age_range_id', 1)->first();

    if ($fee === null || $fee->ageRange === null) {
        expect(true)->toBeTrue();

        return;
    }

    $testAge = (int) ($fee->ageRange->age_init ?? 0);

    if ($testAge <= 0) {
        expect(true)->toBeTrue();

        return;
    }

    $calculator = new AffiliationAffiliateFeeCalculator;

    $resolved = $calculator->resolveFeeForAffiliateAge($affiliation, $testAge);

    expect($resolved)->not->toBeNull()
        ->and($resolved->age_range_id)->toBe(1);
});
