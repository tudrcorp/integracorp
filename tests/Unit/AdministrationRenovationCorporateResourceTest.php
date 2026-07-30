<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;

it('registra el recurso de renovaciones corporativas en afiliaciones sin crear ni editar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RenovationCorporates/RenovationCorporateResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('canEdit')
        ->toContain('ListRenovationCorporates::route')
        ->toContain('ViewRenovationCorporate::route')
        ->not->toContain('CreateRenovationCorporate')
        ->not->toContain('EditRenovationCorporate');
});

it('reutiliza la tabla e infolist compartidos de renovaciones corporativas', function (): void {
    $tableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RenovationCorporates/Tables/RenovationsCorporateTable.php');

    expect($tableSource)
        ->toContain('App\Filament\Shared\RenovationCorporates\RenovationsCorporateTable')
        ->toContain('RenovationCorporateResource::class')
        ->toContain('AffiliationCorporateResource::class');
});

it('define el slug del recurso en el panel administration', function (): void {
    expect(RenovationCorporateResource::getSlug())->toBe('renovation-corporates');
});
