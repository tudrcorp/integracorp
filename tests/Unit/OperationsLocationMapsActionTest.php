<?php

declare(strict_types=1);

it('AffiliateInfolist expone mapa en dirección del afiliado', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Schemas/AffiliateInfolist.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Pages/ViewAffiliate.php');

    expect($infolist)
        ->toContain('OperationsLocationMapAction::forAffiliate()')
        ->toContain("TextEntry::make('address')")
        ->toContain('IOS_ADDRESS_CARD')
        ->toContain('copyMessage(\'Dirección copiada\')')
        ->not->toContain('google.com/maps/search');

    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php');

    expect($page)
        ->toContain('AppliesOperationsAddressFromMaps')
        ->toContain('location-maps-loader');

    expect($trait)->toContain('applyAffiliateLocationFromMaps');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Support/OperationsLocationMapAction.php'))
        ->toContain('OperationsMapSearchAddress::forAffiliate');
});

it('AffiliateCorporateInfolist expone mapa en direcciones de afiliado y corporativo', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporateInfolist.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php');
    $actionClass = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Support/OperationsLocationMapAction.php');

    expect($infolist)
        ->toContain('OperationsLocationMapAction::forAffiliateCorporate()')
        ->toContain('OperationsLocationMapAction::forAffiliationCorporateOnAffiliateCorporate()')
        ->toContain("TextEntry::make('address')")
        ->toContain("TextEntry::make('affiliationCorporate.address')")
        ->toContain('IOS_ADDRESS_CARD')
        ->toContain('Dirección de residencia')
        ->toContain('Dirección de la empresa')
        ->toContain('affiliationCorporate.city.definition')
        ->not->toContain('google.com/maps/search');

    expect($actionClass)
        ->toContain('OperationsMapSearchAddress::forAffiliateCorporate')
        ->toContain('OperationsMapSearchAddress::forAffiliationCorporate');

    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php');

    expect($page)
        ->toContain('AppliesOperationsAddressFromMaps')
        ->toContain('location-maps-loader');

    expect($trait)
        ->toContain('applyAffiliateCorporateLocationFromMaps')
        ->toContain('applyAffiliationCorporateLocationFromMaps');
});

it('OperationsLocationMapAction define acciones para cada entidad', function (): void {
    $actionClass = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Support/OperationsLocationMapAction.php');
    $sharedModal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/shared/location-maps-modal.blade.php');
    $js = file_get_contents(dirname(__DIR__, 2).'/public/js/supplier-location-maps.js');

    expect($actionClass)
        ->toContain('forSupplier')
        ->toContain('forAffiliate')
        ->toContain('forAffiliateCorporate')
        ->toContain('forAffiliationCorporateOnAffiliateCorporate')
        ->toContain('applyAffiliateLocationFromMaps')
        ->toContain('applyAffiliationCorporateLocationFromMaps');

    expect($sharedModal)
        ->toContain('applyLivewireMethod')
        ->toContain('supplier-location-maps-root');

    expect($js)->toContain('applyLivewireMethod');
});
