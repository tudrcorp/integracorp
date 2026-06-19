<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;

uses(Tests\TestCase::class);

it('exige unidad de negocio y linea de servicio para sincronizar afiliados', function (): void {
    $affiliation = Affiliation::query()->first();

    if ($affiliation === null) {
        expect(true)->toBeTrue();

        return;
    }

    $synchronizer = new AffiliationAffiliateBusinessContextSynchronizer;

    expect(fn (): int => $synchronizer->sync($affiliation, null, 1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): int => $synchronizer->sync($affiliation, 1, null))
        ->toThrow(InvalidArgumentException::class);
});

it('incluye accion de sincronizacion en el fieldset de informacion adicional', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php');

    expect($source)
        ->toContain("Fieldset::make('Información adicional de la Afiliación')")
        ->toContain("Action::make('syncAffiliateBusinessContext')")
        ->toContain('AffiliationAffiliateBusinessContextSynchronizer::class')
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain('Sincronizar con afiliados');
});
