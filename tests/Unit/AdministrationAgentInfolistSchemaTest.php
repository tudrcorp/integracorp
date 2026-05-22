<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Agents\Schemas\AgentInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agente en administracion sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa la implementacion de infolist business para agentes en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('\App\Filament\Business\Resources\Agents\Schemas\AgentInfolist::configure($schema)');
});

it('hereda estilos de tabs de telemedicina via business agent infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('TABS_CONTAINER')->toContain('->persistTab()');
});
