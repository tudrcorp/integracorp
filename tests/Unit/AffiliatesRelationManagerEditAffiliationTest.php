<?php

declare(strict_types=1);

it('recalcula afiliación desde suma de tarifas anuales y frecuencia', function () {
    $relationManagerPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php';
    $calculatorPath = dirname(__DIR__, 2).'/app/Support/AffiliationAffiliateFeeCalculator.php';

    expect(file_get_contents($relationManagerPath))
        ->toContain('AffiliationAffiliateFeeCalculator')
        ->toContain('applyAmountsToAffiliate');

    expect(file_get_contents($calculatorPath))
        ->toContain('recalculateAffiliationTotalsFromAffiliates')
        ->toContain('sum(\'fee\')')
        ->toContain('totalAmountForPaymentFrequency($owner->fee_anual')
        ->toContain('where(\'status\', \'ACTIVO\')')
        ->toContain("->where('age_range_id', 1)")
        ->toContain("->where('coverage_id', \$affiliation->coverage_id)")
        ->toContain('affiliateAgeMatchesFeeRange')
        ->toContain('age_init')
        ->toContain('(float) $fee->price');
});
