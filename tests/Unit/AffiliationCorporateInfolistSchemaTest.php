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
        ->toContain("Section::make('Pagos e ILS')")
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
