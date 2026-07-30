<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;

it('registra el histórico de renovaciones corporativas en administración', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporateRenovationHistories/AffiliationCorporateRenovationHistoryResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('ListAffiliationCorporateRenovationHistories::route')
        ->toContain('ViewAffiliationCorporateRenovationHistory::route');
});

it('reutiliza la tabla compartida de histórico corporativo', function (): void {
    $tableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporateRenovationHistories/Tables/AffiliationCorporateRenovationHistoriesTable.php');

    expect($tableSource)
        ->toContain('App\Filament\Shared\RenovationCorporateHistories\RenovationCorporateHistoriesTable')
        ->toContain('AffiliationCorporateRenovationHistoryResource::class');
});

it('define el slug del histórico corporativo en administration', function (): void {
    expect(AffiliationCorporateRenovationHistoryResource::getSlug())->toBe('affiliation-corporate-renovation-histories');
});
