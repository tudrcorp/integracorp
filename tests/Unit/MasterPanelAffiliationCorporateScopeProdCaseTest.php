<?php

declare(strict_types=1);

use App\Support\CommercialStructure\MasterPanelAffiliationCorporateScope;

/**
 * Caso real verificado contra respaldo producción 2026-09-14 (TDEC-COR-00056 / SEGUROPARATI TDG-201).
 */
it('el respaldo de produccion TDEC-COR-00056 entra con alcance nuevo y no con el filtro legacy', function (): void {
    $masterCode = 'TDG-201';
    $network = [$masterCode];

    $ownerCode = 'TDG-100';
    $codeAgency = 'TDG-201';

    $legacyMatches = $ownerCode === $masterCode;
    $newMatches = MasterPanelAffiliationCorporateScope::recordMatchesNetwork($ownerCode, $codeAgency, $network);

    expect($legacyMatches)->toBeFalse()
        ->and($newMatches)->toBeTrue();
});
