<?php

declare(strict_types=1);

it('recalcula afiliación desde suma de tarifas anuales y frecuencia', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('recalculateAffiliationTotalsFromAffiliates')
        ->toContain('sum(\'fee\')')
        ->toContain('totalAmountForPaymentFrequency($sumAnnualFees')
        ->toContain('where(\'status\', \'ACTIVO\')');
});
