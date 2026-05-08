<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('expone la relación observationCommercialStructures en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("RepeatableEntry::make('observationCommercialStructures')");
});

it('formatea fechas legadas d/m/Y con FilamentDateDisplay en lugar de TextEntry::date', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('FilamentDateDisplay::toDmy');
    expect($source)->not->toContain("->date('d/m/Y')");
});

it('agrupa secciones alineadas al formulario y usa rejilla de cinco columnas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Section::make('Información general de la agencia')");
    expect($source)->toContain("Section::make('Contacto alternativo')");
    expect($source)->toContain('Grid::make(5)');
});
