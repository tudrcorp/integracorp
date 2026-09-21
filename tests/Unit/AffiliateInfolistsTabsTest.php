<?php

declare(strict_types=1);

it('infolist de afiliado usa pestañas', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Schemas/AffiliateInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain("Tab::make('Datos personales')")
        ->toContain("Tab::make('Afiliación')")
        ->toContain("Tab::make('Beneficios del plan')")
        ->toContain("Tab::make('Cuestionario médico')")
        ->toContain('affiliateInfolistTabs')
        ->toContain('OperationsAffiliatePlanBenefitsCard::viewData')
        ->toContain("View::make('filament.operations.affiliates.plan-benefits-clinical')")
        ->not->toContain("TextEntry::make('plan.benefitPlans.description')");
});

it('infolist de afiliado corporativo usa pestañas', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporateInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain("Tab::make('Datos del afiliado')")
        ->toContain("Tab::make('Beneficios del plan')")
        ->toContain('affiliateCorporateInfolistTabs')
        ->toContain('IOS_ADDRESS_CARD')
        ->toContain('OperationsAffiliatePlanBenefitsCard::viewData')
        ->toContain("View::make('filament.operations.affiliates.plan-benefits-clinical')")
        ->not->toContain("TextEntry::make('plan.benefitPlans.description')");
});
