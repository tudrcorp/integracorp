<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgents\Widgets\TotalTravelAgentStatOverview;

it('usa vista glass iOS y una columna para la stat', function (): void {
    $ref = new ReflectionClass(TotalTravelAgentStatOverview::class);

    $columns = (new ReflectionMethod(TotalTravelAgentStatOverview::class, 'getColumns'))
        ->invoke(new TotalTravelAgentStatOverview);

    expect($columns)->toBe(3);
});

it('ocupa el ancho completo del área de widgets del listado', function (): void {
    $ref = new ReflectionClass(TotalTravelAgentStatOverview::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full');
});

it('incluye el tema glass para agentes de viaje', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    expect($css)->not->toBeFalse()
        ->and($css)->toContain('.fi-travel-agent-stats-overview-glass .fi-wi-stats-overview-stat');
});
