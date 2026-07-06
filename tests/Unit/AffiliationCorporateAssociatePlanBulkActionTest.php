<?php

declare(strict_types=1);

it('expone la acción masiva asociar y recalcular con formulario de afiliados y servicios de sincronización', function (): void {
    $root = dirname(__DIR__, 2);
    $rm = file_get_contents($root.'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/AffiliationCorporatePlansRelationManager.php');
    expect($rm)->toContain("BulkAction::make('associate_and_recalculate')")
        ->and($rm)->toContain('CheckboxList::make(\'affiliate_ids\')')
        ->and($rm)->toContain('function (array $data, Collection $records)')
        ->and($rm)->not->toContain('assignment_mode')
        ->and($rm)->not->toContain('auto_by_age')
        ->and($rm)->toContain('idsForAffiliatesMatchingPlanRowAgeRange')
        ->and($rm)->toContain('AssociateAffiliatesWithCorporatePlanService::run')
        ->and($rm)->toContain('\'plan_id\' => $planRow->plan_id')
        ->and($rm)->toContain('\'fee\' => $planRow->fee')
        ->and($rm)->toContain('resolveAssociateModalPlanRow')
        ->and($rm)->toContain('->options(function (Get $get): array {')
        ->and($rm)->toContain('->whereIn(\'id\', $eligibleIds)')
        ->and($rm)->toContain('fi-ios-affiliation-associate-plan-modal');

    expect(file_get_contents($root.'/app/Services/AssociateAffiliatesWithCorporatePlanService.php'))
        ->toContain('idsForAffiliatesMatchingPlanRowAgeRange')
        ->and(file_get_contents($root.'/app/Services/CorporateAffiliatePlanSyncService.php'))
        ->toContain('syncPlanRowTotalsFromAffiliates');

    expect(file_get_contents($root.'/resources/css/filament/admin/theme.css'))
        ->toContain('.fi-ios-affiliation-associate-plan-modal');
});
