<?php

declare(strict_types=1);

use App\Support\CommercialStructure\MasterPanelAffiliationCorporateScope;

it('incluye venta directa de master bajo TDG cuando owner_code es TDG-100', function (): void {
    $network = ['TDG-250', 'TDG-301'];

    expect(MasterPanelAffiliationCorporateScope::recordMatchesNetwork('TDG-100', 'TDG-250', $network))->toBeTrue()
        ->and(MasterPanelAffiliationCorporateScope::recordMatchesNetwork('TDG-250', 'TDG-250', $network))->toBeTrue()
        ->and(MasterPanelAffiliationCorporateScope::recordMatchesNetwork('TDG-250', 'TDG-301', $network))->toBeTrue();
});

it('excluye afiliaciones de otra master con el filtro antiguo equivalente', function (): void {
    $network = ['TDG-250', 'TDG-301'];
    $legacyOwnerOnly = fn (string $owner, string $code): bool => $owner === 'TDG-250';

    expect($legacyOwnerOnly('TDG-100', 'TDG-250'))->toBeFalse()
        ->and(MasterPanelAffiliationCorporateScope::recordMatchesNetwork('TDG-100', 'TDG-250', $network))->toBeTrue();
});

it('no mezcla afiliaciones ajenas solo por owner_code TDG-100 sin code_agency en la red', function (): void {
    $network = ['TDG-250'];

    expect(MasterPanelAffiliationCorporateScope::recordMatchesNetwork('TDG-100', 'TDG-999', $network))->toBeFalse();
});

it('la tabla master de afiliaciones corporativas usa el alcance de red comercial', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain('MasterPanelAffiliationCorporateScope::apply')
        ->not->toContain("->where('owner_code', Auth::user()->code_agency)");
});

it('documenta owner_code TDG-100 con code_agency de la master en el alcance', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/CommercialStructure/MasterPanelAffiliationCorporateScope.php');

    expect($source)
        ->toContain('orWhereIn(\'code_agency\'')
        ->toContain('whereIn(\'owner_code\'');
});
