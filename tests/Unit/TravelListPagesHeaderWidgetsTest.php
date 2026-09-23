<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TotalTravelAgencyStatOverview;
use App\Filament\Business\Resources\TravelAgents\Pages\ListTravelAgents;
use App\Filament\Business\Resources\TravelAgents\Widgets\TotalTravelAgentStatOverview;

it('lista agencias de viaje usa el encabezado de tu doctor en viajes', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Pages/ListTravelAgencies.php');

    expect($source)
        ->toContain('logo-tdev.png')
        ->toContain('Tu Doctor En Viajes')
        ->toContain('Agencias de viajes')
        ->toContain('Estructura comercial · agencias, asociadas y agentes')
        ->toContain('public function getTitle(): string|Htmlable');
});

it('lista agencias de viaje solo monta el widget de agencias en la cabecera', function (): void {
    $ref = new ReflectionClass(ListTravelAgencies::class);
    $instance = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('getHeaderWidgets');
    $method->setAccessible(true);

    expect($method->invoke($instance))->toBe([
        TotalTravelAgencyStatOverview::class,
    ]);
});

it('lista agentes de viaje solo monta el widget de agentes en la cabecera', function (): void {
    $ref = new ReflectionClass(ListTravelAgents::class);
    $instance = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('getHeaderWidgets');
    $method->setAccessible(true);

    expect($method->invoke($instance))->toBe([
        TotalTravelAgentStatOverview::class,
    ]);
});
