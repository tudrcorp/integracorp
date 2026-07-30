<?php

declare(strict_types=1);

use App\Filament\Business\Resources\RenovationCorporates\RenovationCorporateResource;

it('registra el recurso de renovaciones corporativas en negocios sin crear ni editar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/RenovationCorporates/RenovationCorporateResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('ListRenovationCorporates::route')
        ->toContain('ViewRenovationCorporate::route');
});

it('exporta csv en el panel business', function (): void {
    $tableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/RenovationCorporates/Tables/RenovationsCorporateTable.php');

    expect($tableSource)
        ->toContain('business.renovation-corporates.export-csv');
});

it('define el slug del recurso en el panel business', function (): void {
    expect(RenovationCorporateResource::getSlug())->toBe('renovation-corporates');
});
