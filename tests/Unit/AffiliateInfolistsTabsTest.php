<?php

declare(strict_types=1);

it('infolist de afiliado usa pestañas', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Schemas/AffiliateInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain("Tab::make('Resumen')")
        ->toContain("Tab::make('Afiliación')")
        ->toContain('affiliateInfolistTabs');
});

it('infolist de afiliado corporativo usa pestañas', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporateInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain("Tab::make('Datos del afiliado')")
        ->toContain('affiliateCorporateInfolistTabs');
});
