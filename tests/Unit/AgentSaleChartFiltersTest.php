<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\TotalSaleForAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;

uses(Tests\TestCase::class);

it('TotalSaleForAgent no expone opciones de filtro por estado geográfico', function () {
    expect(method_exists(TotalSaleForAgent::class, 'getChartStateOptions'))->toBeFalse();
});

it('TotalSaleMonthlyNowVsLastAgent no incluye «Todo el año» en el desplegable de mes', function () {
    $widget = new TotalSaleMonthlyNowVsLastAgent;
    $widget->filterYear = (string) now()->year;

    $options = $widget->getChartMonthOptions();

    expect($options)->not->toHaveKey('0')
        ->and($options)->toHaveKey('1');
});
