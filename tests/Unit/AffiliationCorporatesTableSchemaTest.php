<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;

it('define el configurador de tabla de afiliaciones corporativas', function (): void {
    expect(method_exists(AffiliationCorporatesTable::class, 'configure'))->toBeTrue();
});

it('expone la accion de regenerar documentos sin restringirla a superadmin', function (): void {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'
    );

    $actionPosition = strpos($source, "Action::make('regenerate')");
    $downloadPosition = strpos($source, "Action::make('download')");

    expect($actionPosition)->not->toBeFalse()
        ->and($downloadPosition)->not->toBeFalse()
        ->and($actionPosition)->toBeLessThan($downloadPosition);

    $regenerateBlock = substr($source, $actionPosition, $downloadPosition - $actionPosition);

    expect($regenerateBlock)
        ->toContain('affiliation-corporate-documents-preview-modal')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_DOCUMENTS_REGENERATE_OPENED')
        ->not->toContain('SUPERADMIN');
});
