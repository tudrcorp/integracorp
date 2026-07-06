<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliación corporativa sin error', function (): void {
    $schema = Schema::make();
    $configured = AffiliationCorporateInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('mueve la documentación fuera del infolist principal', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toContain("Section::make('Documentación')")
        ->toContain("Section::make('Pagos')")
        ->toContain("Section::make('Observaciones')");
});

it('muestra el documento del titular en ubicación y datos fiscales', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('document')")
        ->toContain("->label('Documento del titular')");
});

it('incluye el tab de planes asociados con detalle', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Planes asociados')")
        ->toContain("RepeatableEntry::make('affiliationCorporatePlans')")
        ->toContain("TextEntry::make('plan.description')")
        ->toContain("TextEntry::make('coverage.price')")
        ->toContain("TextEntry::make('ageRange.range')")
        ->toContain("TextEntry::make('subtotal_monthly')");
});

it('incluye un tab con el documento del contratante', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Documento del contratante')")
        ->toContain("ImageEntry::make('document')")
        ->toContain('->imageHeight(260)')
        ->toContain('documentIsImage');
});

it('muestra unidad y linea de negocio en afiliados asociados del infolist corporativo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('businessUnit.definition')")
        ->toContain("TextEntry::make('businessLine.definition')")
        ->toContain("TableColumn::make('Unidad')")
        ->toContain("TableColumn::make('Línea')")
        ->toContain('affiliateBusinessContextColor')
        ->toContain('AffiliateCorporate $record')
        ->toContain('ViewRecord $livewire')
        ->toContain("->weight('semibold')");
});

it('muestra las miniaturas de documento y documento ILS en afiliados corporativos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TableColumn::make('Documento')")
        ->toContain("TableColumn::make('Doc. ILS')")
        ->toContain("ImageEntry::make('document')")
        ->toContain("ImageEntry::make('document_ils')")
        ->toContain('->imageHeight(40)');
});

it('contiene la tabla de afiliados asociados en un contenedor con scroll horizontal', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('IOS_REPEATABLE_TABLE_SCROLL_CLASS')
        ->toContain('overflow-x-auto')
        ->toContain("RepeatableEntry::make('corporateAffiliates')")
        ->toContain('min-w-0 overflow-hidden')
        ->toContain("TableColumn::make('Nombre')->width('13%')");
});

it('muestra la informacion del voucher ils en afiliados asociados del infolist corporativo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('vaucherIls')")
        ->toContain("TableColumn::make('Código ILS')")
        ->toContain("TextEntry::make('dateInit')")
        ->toContain("TextEntry::make('dateEnd')")
        ->not->toContain("TableColumn::make('Estado ILS')")
        ->not->toContain("TableColumn::make('Días restantes')");
});
