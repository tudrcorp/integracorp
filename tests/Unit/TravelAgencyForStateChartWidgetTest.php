<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TravelAgencyForStateChart;

it('usa barras horizontales estilo proveedores y ancho completo', function (): void {
    $ref = new ReflectionClass(TravelAgencyForStateChart::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full');
    expect($ref->getDefaultProperties()['maxHeight'] ?? null)->toBe('440px');

    $widget = new TravelAgencyForStateChart;

    $type = (new ReflectionMethod(TravelAgencyForStateChart::class, 'getType'))->invoke($widget);
    expect($type)->toBe('bar');

    $tablePage = (new ReflectionMethod(TravelAgencyForStateChart::class, 'getTablePage'))->invoke($widget);
    expect($tablePage)->toBe(ListTravelAgencies::class);

    $options = (new ReflectionMethod(TravelAgencyForStateChart::class, 'getOptions'))->invoke($widget);
    expect($options['indexAxis'] ?? null)->toBe('y');
});
