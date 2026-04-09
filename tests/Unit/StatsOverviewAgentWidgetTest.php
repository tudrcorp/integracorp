<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\StatsOverviewAgent;

it('usa la vista con contenedor liquid glass iOS', function () {
    $view = (new ReflectionClass(StatsOverviewAgent::class))->getDefaultProperties()['view'] ?? null;

    expect($view)->toBe('filament.widgets.stats-overview-agent-ios');
});

it('define grid de 2 columnas desde md para que la tarjeta ocupe mitad del ancho', function () {
    $method = new ReflectionMethod(StatsOverviewAgent::class, 'getColumns');

    expect($method->invoke(new StatsOverviewAgent))->toBe([
        'default' => 1,
        'md' => 2,
    ]);
});
