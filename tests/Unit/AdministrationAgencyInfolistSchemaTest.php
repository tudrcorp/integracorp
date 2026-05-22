<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia en administracion sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa la implementacion de infolist business para agencias en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('\App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist::configure($schema)');
});

it('hereda estilos de tabs de telemedicina via business agency infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('TABS_CONTAINER')->toContain('->persistTab()');
});
