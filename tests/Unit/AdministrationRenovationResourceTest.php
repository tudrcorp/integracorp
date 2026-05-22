<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Renovations\RenovationResource;

it('registra el recurso de renovaciones en afiliaciones sin crear ni editar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Renovations/RenovationResource.php');

    expect($source)
        ->toContain("navigationGroup = 'AFILIACIONES'")
        ->toContain('canCreate(): bool')
        ->toContain('return false')
        ->toContain('canEdit')
        ->toContain('ListRenovations::route')
        ->toContain('ViewRenovation::route')
        ->not->toContain('CreateRenovation')
        ->not->toContain('EditRenovation');
});

it('reutiliza la tabla e infolist compartidos de renovaciones', function (): void {
    $tableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Renovations/Tables/RenovationsTable.php');

    expect($tableSource)
        ->toContain('App\Filament\Shared\Renovations\RenovationsTable')
        ->toContain('RenovationResource::class')
        ->toContain('AffiliationResource::class');
});

it('define el slug del recurso en el panel administration', function (): void {
    expect(RenovationResource::getSlug())->toBe('renovations');
});
