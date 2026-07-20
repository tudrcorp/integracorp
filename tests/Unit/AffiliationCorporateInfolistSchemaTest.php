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

it('muestra unidad y linea de negocio en el tab de negocio del infolist corporativo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('businessUnit.definition')")
        ->toContain("TextEntry::make('businessLine.definition')")
        ->toContain("Tab::make('Negocio')")
        ->toContain('ViewRecord $livewire')
        ->not->toContain("RepeatableEntry::make('corporateAffiliates')");
});

it('mantiene el documento del contratante como ImageEntry sin tabla de afiliados en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("ImageEntry::make('document')")
        ->toContain('->imageHeight(260)')
        ->not->toContain("ImageEntry::make('document_ils')")
        ->not->toContain("TableColumn::make('Doc. ILS')");
});

it('no incluye la poblacion de afiliados en el infolist para evitar renders pesados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toContain("RepeatableEntry::make('corporateAffiliates')")
        ->not->toContain('IOS_REPEATABLE_TABLE_SCROLL_CLASS')
        ->toContain("->livewireProperty('affiliationCorporateInfolistTab')");
});

it('muestra pagos y voucher ils en tabs dedicados sin listar afiliados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Pagos')")
        ->toContain("TextEntry::make('payment_frequency')")
        ->toContain("TextEntry::make('fee_anual')")
        ->not->toContain("TextEntry::make('vaucherIls')")
        ->not->toContain("TableColumn::make('Código ILS')");
});
