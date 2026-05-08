<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Widgets\TotalTravelAgencyStatOverview;

it('usa el mismo layout base que stats overview de agencias', function (): void {
    $ref = new ReflectionClass(TotalTravelAgencyStatOverview::class);

    expect($ref->getDefaultProperties()['heading'] ?? null)->toBeNull()
        ->and($ref->getDefaultProperties()['description'] ?? null)->toBeNull();

    $columns = (new ReflectionMethod(TotalTravelAgencyStatOverview::class, 'getColumns'))
        ->invoke(new TotalTravelAgencyStatOverview);

    expect($columns)->toBe(3);

    $method = new ReflectionMethod(TotalTravelAgencyStatOverview::class, 'getSectionContentComponent');
    expect($method->isPublic())->toBeTrue();
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

it('permite tableColumnSearches nulo para props reactivas de Livewire (PHP 8.4)', function (): void {
    $prop = (new ReflectionClass(TotalTravelAgencyStatOverview::class))->getProperty('tableColumnSearches');

    expect($prop->getType())->not->toBeNull()
        ->and($prop->getType()->allowsNull())->toBeTrue();
});
