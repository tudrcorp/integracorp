<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\Tables\AffiliateCorporatesTable;
use App\Filament\Operations\Resources\Affiliates\Tables\AffiliatesTable;

it('define el configurador de tabla de afiliados operations', function (): void {
    expect(method_exists(AffiliatesTable::class, 'configure'))->toBeTrue();
});

it('incluye linea y unidad de negocio en la tabla de afiliados individuales', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');

    expect($source)
        ->toContain("TextColumn::make('affiliation.business_line_id')")
        ->toContain("TextColumn::make('affiliation.business_unit_id')")
        ->toContain('affiliation.businessLine:id,definition')
        ->toContain('affiliation.businessUnit:id,definition');
});

it('define el configurador de tabla de afiliados corporativos operations', function (): void {
    expect(method_exists(AffiliateCorporatesTable::class, 'configure'))->toBeTrue();
});

it('incluye linea y unidad de negocio en la tabla de afiliados corporativos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php');

    expect($source)
        ->toContain("TextColumn::make('affiliationCorporate.business_line_id')")
        ->toContain("TextColumn::make('affiliationCorporate.business_unit_id')")
        ->toContain('affiliationCorporate.businessLine:id,definition')
        ->toContain('affiliationCorporate.businessUnit:id,definition');
});
