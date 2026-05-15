<?php

declare(strict_types=1);

it('configura búsqueda global en panel operaciones y recursos clave', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php';
    $provider = file_get_contents($providerPath);
    expect($provider)->not->toBeFalse()
        ->and($provider)->toContain('->globalSearch(')
        ->and($provider)->toContain('globalSearchKeyBindings')
        ->and($provider)->toContain('globalSearchDebounce');

    $paths = [
        'DoctorNurseResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/DoctorNurseResource.php',
        'SupplierResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/SupplierResource.php',
        'AffiliateResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/AffiliateResource.php',
        'AffiliateCorporateResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/AffiliateCorporateResource.php',
    ];

    foreach ($paths as $path) {
        $src = file_get_contents($path);
        expect($src)->not->toBeFalse()
            ->and($src)->toContain('getGloballySearchableAttributes')
            ->and($src)->toContain('getGlobalSearchResultDetails')
            ->and($src)->toContain('getGlobalSearchEloquentQuery');
    }
});
