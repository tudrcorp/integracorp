<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Widgets\TotalTravelAgencyStatOverview;

it('usa vista glass iOS y una columna para la stat', function (): void {
    $ref = new ReflectionClass(TotalTravelAgencyStatOverview::class);

    expect($ref->getDefaultProperties()['view'] ?? null)
        ->toBe('filament.widgets.stats-overview-travel-agency-glass');

    $columns = (new ReflectionMethod(TotalTravelAgencyStatOverview::class, 'getColumns'))
        ->invoke(new TotalTravelAgencyStatOverview);

    expect($columns)->toBe(1);
});

it('ocupa el ancho completo del área de widgets del listado', function (): void {
    $ref = new ReflectionClass(TotalTravelAgencyStatOverview::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full');
});

it('incluye el tema glass para agencias de viaje', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    expect($css)->not->toBeFalse()
        ->and($css)->toContain('.fi-travel-agency-stats-overview-glass .fi-wi-stats-overview-stat');
});
