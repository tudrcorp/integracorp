<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliación individual sin error', function (): void {
    $schema = Schema::make();
    $configured = AffiliationInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('no incluye la pestaña de afiliados asociados en el infolist individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->not->toContain("Tab::make('Afiliados asociados')")
        ->not->toContain("RepeatableEntry::make('affiliates')")
        ->not->toContain('affiliateBusinessContextColor')
        ->not->toContain('AffiliationInfolistTab::AFILIADOS');
});

it('incluye un tab con el documento del titular', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("Tab::make('Documento del titular')")
        ->toContain("TextEntry::make('document')")
        ->toContain("asset('storage/'.\$record->document)");
});

it('muestra una miniatura grande del documento del titular cuando es imagen', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("ImageEntry::make('document')")
        ->toContain('->imageHeight(260)')
        ->toContain('documentIsImage');
});
