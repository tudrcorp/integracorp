<?php

declare(strict_types=1);

it('SupplierInfolist expone acción de mapa en dirección principal', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierInfolist.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/shared/location-maps-modal.blade.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php');

    expect($infolist)
        ->toContain('OperationsLocationMapAction::forSupplier')
        ->toContain("TextEntry::make('ubicacion_principal')")
        ->toContain('suffixAction(OperationsLocationMapAction::forSupplier())');

    expect($view)
        ->toContain('supplier-location-maps-canvas')
        ->toContain('-maps-config')
        ->toContain('application/json')
        ->toContain('recordLabel')
        ->toContain('-destination-address')
        ->toContain('operations-map-field')
        ->toContain('Clic en el mapa, un establecimiento')
        ->toContain('-route-panel')
        ->toContain('-route-btn')
        ->toContain('-use-destination')
        ->toContain('gm-style-iw')
        ->toContain('Establecimientos a mostrar');

    $loader = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/shared/location-maps-loader.blade.php');
    $js = file_get_contents(dirname(__DIR__, 2).'/public/js/supplier-location-maps.js');

    expect($loader)->toContain('supplier-location-maps.js');
    expect($js)
        ->toContain('applyPoiFilterPillStyle')
        ->toContain('isDarkMode')
        ->toContain('refreshFilterStyles')
        ->toContain('buildInfoWindowContent')
        ->toContain('escapeHtml')
        ->toContain('openInfoWindow')
        ->toContain('parseMapsConfig')
        ->toContain('isGoogleMapsReady')
        ->toContain('waitForGoogleMapsReady')
        ->toContain('&libraries=places&callback=')
        ->not->toContain('&libraries=places&loading=async')
        ->toContain('searchNearbyEstablishments')
        ->toContain('farmacia')
        ->toContain('nearbySearch')
        ->toContain('placeSupplierMarker')
        ->toContain('drawRouteTo')
        ->toContain('selectDestination')
        ->toContain('DirectionsService')
        ->toContain('suppressMarkers')
        ->toContain('drawFallbackRoute')
        ->toContain('directionsErrorMessage')
        ->toContain('markDirectionsApiDenied')
        ->toContain('Directions API');

    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php');

    expect($page)
        ->toContain('location-maps-loader')
        ->toContain('AppliesOperationsAddressFromMaps');

    expect($trait)->toContain('applySupplierLocationFromMaps');
});

it('config services define google_maps', function (): void {
    $services = file_get_contents(dirname(__DIR__, 2).'/config/services.php');

    expect($services)
        ->toContain("'google_maps'")
        ->toContain('GOOGLE_MAPS_API_KEY')
        ->toContain('GOOGLE_MAPS_DEFAULT_LAT')
        ->toContain('GOOGLE_MAPS_DEFAULT_LNG');
});
