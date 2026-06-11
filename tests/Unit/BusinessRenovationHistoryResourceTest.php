<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;

it('registra el recurso de histórico de renovaciones en afiliaciones sin crear ni editar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationRenovationHistories/AffiliationRenovationHistoryResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('Histórico de renovaciones')
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('canEdit')
        ->toContain('ListAffiliationRenovationHistories::route')
        ->toContain('ViewAffiliationRenovationHistory::route')
        ->not->toContain('CreateAffiliationRenovationHistory')
        ->not->toContain('EditAffiliationRenovationHistory');
});

it('reutiliza la tabla e infolist compartidos del histórico de renovaciones en business', function (): void {
    $tableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationRenovationHistories/Tables/AffiliationRenovationHistoriesTable.php');

    expect($tableSource)
        ->toContain('App\Filament\Shared\RenovationHistories\RenovationHistoriesTable')
        ->toContain('AffiliationRenovationHistoryResource::class')
        ->toContain('AffiliationResource::class');
});

it('define el slug del recurso de histórico en el panel business', function (): void {
    expect(AffiliationRenovationHistoryResource::getSlug())->toBe('affiliation-renovation-histories');
});
