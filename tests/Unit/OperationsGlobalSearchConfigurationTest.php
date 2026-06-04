<?php

declare(strict_types=1);

it('configura búsqueda global en panel operaciones y recursos clave', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php';
    $provider = file_get_contents($providerPath);
    expect($provider)->not->toBeFalse()
        ->and($provider)->toContain('->globalSearch(')
        ->and($provider)->toContain('globalSearchKeyBindings')
        ->and($provider)->toContain('globalSearchDebounce');

    $supplierPaths = [
        'SupplierResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/SupplierResource.php',
        'DoctorNurseResource' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/DoctorNurseResource.php',
    ];

    $otherPaths = [
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/AffiliateResource.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/AffiliateCorporateResource.php',
    ];

    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Concerns/ConfiguresOperationsSupplierGlobalSearch.php');

    expect($trait)->not->toBeFalse()
        ->and($trait)->toContain('getGloballySearchableAttributes')
        ->and($trait)->toContain('getGlobalSearchResultDetails')
        ->and($trait)->toContain('getGlobalSearchResults')
        ->and($trait)->toContain('GlobalSearchSupplierQuery::applyToQuery');

    foreach ($supplierPaths as $path) {
        $src = file_get_contents($path);
        expect($src)->not->toBeFalse()
            ->and($src)->toContain('ConfiguresOperationsSupplierGlobalSearch')
            ->and($src)->toContain('getGlobalSearchEloquentQuery');
    }

    foreach ($otherPaths as $path) {
        $src = file_get_contents($path);
        expect($src)->not->toBeFalse()
            ->and($src)->toContain('getGloballySearchableAttributes')
            ->and($src)->toContain('getGlobalSearchResultDetails')
            ->and($src)->toContain('getGlobalSearchEloquentQuery');
    }

    expect(file_get_contents($supplierPaths['SupplierResource']))
        ->toContain("return 'juridico'");

    expect(file_get_contents($supplierPaths['DoctorNurseResource']))
        ->toContain("return 'natural'");
});
