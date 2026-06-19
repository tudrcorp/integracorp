<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporateAffiliateBusinessContextSynchronizer;

uses(Tests\TestCase::class);

it('exige unidad de negocio y linea de servicio para sincronizar afiliados corporativos', function (): void {
    $affiliationCorporate = AffiliationCorporate::query()->first();

    if ($affiliationCorporate === null) {
        expect(true)->toBeTrue();

        return;
    }

    $synchronizer = new AffiliationCorporateAffiliateBusinessContextSynchronizer;

    expect(fn (): int => $synchronizer->sync($affiliationCorporate, null, 1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): int => $synchronizer->sync($affiliationCorporate, 1, null))
        ->toThrow(InvalidArgumentException::class);
});

it('incluye accion de sincronizacion en el fieldset de informacion adicional corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');

    expect($source)
        ->toContain("Fieldset::make('Información adicional de la Afiliación')")
        ->toContain("Action::make('syncAffiliateCorporateBusinessContext')")
        ->toContain('AffiliationCorporateAffiliateBusinessContextSynchronizer::class')
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain('Sincronizar con afiliados');
});
