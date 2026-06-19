<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliación individual sin error', function (): void {
    $schema = Schema::make();
    $configured = AffiliationInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('muestra unidad y linea de negocio en afiliados asociados del infolist', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("TextEntry::make('businessUnit.definition')")
        ->toContain("TextEntry::make('businessLine.definition')")
        ->toContain("TableColumn::make('Unidad de negocio')")
        ->toContain("TableColumn::make('Línea de servicio')")
        ->toContain('affiliateBusinessContextColor')
        ->toContain('ViewRecord $livewire')
        ->toContain("->weight('semibold')");
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

it('muestra la miniatura del documento de cada afiliado en la tabla', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("TableColumn::make('Documento')")
        ->toContain('->imageHeight(56)');
});

it('muestra la miniatura del documento ILS de cada afiliado en la tabla', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("TableColumn::make('Documento ILS')")
        ->toContain("ImageEntry::make('document_ils')");
});
