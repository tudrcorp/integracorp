<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;

it('registra el histórico de renovaciones corporativas en negocios', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporateRenovationHistories/AffiliationCorporateRenovationHistoryResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('ListAffiliationCorporateRenovationHistories::route')
        ->toContain('ViewAffiliationCorporateRenovationHistory::route');
});

it('define el slug del histórico corporativo en business', function (): void {
    expect(AffiliationCorporateRenovationHistoryResource::getSlug())->toBe('affiliation-corporate-renovation-histories');
});
