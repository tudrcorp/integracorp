<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Administration\Resources\Affiliations\Schemas\AffiliationInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de afiliacion individual en administracion', function (): void {
    $schema = Schema::make();
    $configured = AffiliationInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('delega infolist individual de administracion al de business', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)->toContain('\App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist::configure($schema)');
});

it('configura el infolist de afiliacion corporativa en administracion', function (): void {
    $schema = Schema::make();
    $configured = AffiliationCorporateInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('delega infolist corporativa de administracion al de business', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)->toContain('\App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist::configure($schema)');
});

it('enlace de cotizacion corporativa usa panel business', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)->toContain("panel: 'business'");
});

it('infolist individual usa contenedor de tabs con render Livewire del tab activo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("->livewireProperty('affiliationInfolistTab')")
        ->not->toContain('->persistTab()');
});

it('infolist corporativa usa contenedor de tabs con render Livewire del tab activo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("->livewireProperty('affiliationCorporateInfolistTab')")
        ->not->toContain('->persistTab()');
});
