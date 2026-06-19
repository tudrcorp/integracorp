<?php

declare(strict_types=1);

it('mejora UX de tabla de familiares afiliados en business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('emptyStateHeading')
        ->toContain('->striped()')
        ->toContain('SelectFilter::make')
        ->toContain("TextColumn::make('business_unit_id')")
        ->toContain("TextColumn::make('business_line_id')")
        ->toContain('businessLine:id,definition')
        ->toContain('businessUnit:id,definition')
        ->toContain('affiliateBusinessContextIsSynced')
        ->toContain("IconColumn::make('sync_status')")
        ->toContain('->trueIcon(')
        ->toContain('->falseIcon(')
        ->not->toContain('TextInputColumn::make')
        ->toContain('AffiliationAffiliateFeeCalculator');
});

it('mejora UX de tabla de pagos registrados en business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('emptyStateHeading')
        ->toContain('->striped()')
        ->toContain('ActionGroup::make')
        ->toContain("panel: 'business'")
        ->toContain('FilamentDateDisplay::toDmy');
});
