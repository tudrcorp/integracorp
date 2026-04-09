<?php

declare(strict_types=1);

it('valida tarifa plana sin cobertura y sincroniza afiliados con coverage nulo', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Services/AssociateAffiliatesWithCorporatePlanService.php'))
        ->toContain('normalizeOptionalCoverageId')
        ->and(file_get_contents($root.'/app/Services/AssociateAffiliatesWithCorporatePlanService.php'))
        ->toContain('feeMatchesAgeRangeAndCoverage')
        ->and(file_get_contents($root.'/app/Services/AssociateAffiliatesWithCorporatePlanService.php'))
        ->toContain('assertAffiliatesWithinPlanAgeRange')
        ->and(file_get_contents($root.'/app/Services/AssociateAffiliatesWithCorporatePlanService.php'))
        ->toContain('idsForAffiliatesMatchingPlanRowAgeRange');

    expect(file_get_contents($root.'/app/Services/CorporateAffiliatePlanSyncService.php'))
        ->toContain('applyAffiliateCoverageScope')
        ->and(file_get_contents($root.'/app/Services/CorporateAffiliatePlanSyncService.php'))
        ->toContain('whereNull(\'coverage_id\')');
});
