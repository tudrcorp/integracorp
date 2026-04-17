<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\AgentActiveForEstructureChart;
use App\Filament\Business\Resources\Agencies\Widgets\TotalEstructureAgency;
use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;

uses(Tests\TestCase::class);

it('no expone filtro de estado en los gráficos de estructura/ventas de agencias', function (string $class) {
    $widget = new $class;

    expect($widget->getChartStateSelectOptions())->toBe([]);
})->with([
    TotalEstructureAgency::class,
    AgentActiveForEstructureChart::class,
    TotalSaleForEstructureChart::class,
]);
