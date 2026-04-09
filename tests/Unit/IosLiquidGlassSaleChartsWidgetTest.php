<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\TotalSaleForAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;
use Filament\Support\RawJs;

it('usa opciones Chart.js tipo proveedor y moneda en TotalSaleForAgent', function () {
    $method = new ReflectionMethod(TotalSaleForAgent::class, 'getOptions');
    $options = $method->invoke(new TotalSaleForAgent);

    expect($options)->toBeInstanceOf(RawJs::class);

    $js = $options->toHtml();

    expect($js)->toContain('easeOutQuart')
        ->and($js)->toContain('rgba(22, 22, 24, 0.56)')
        ->and($js)->toContain('categoryPercentage: 0.72');
});

it('usa vista compartida de barras y ancho completo en gráficos de ventas', function (string $class) {
    $reflection = new ReflectionClass($class);
    $view = $reflection->getDefaultProperties()['view'] ?? null;
    $columnSpan = $reflection->getDefaultProperties()['columnSpan'] ?? null;
    $maxHeight = $reflection->getDefaultProperties()['maxHeight'] ?? null;

    expect($view)->toBe('filament.widgets.ios-liquid-glass-bar-chart-widget')
        ->and($columnSpan)->toBe('full')
        ->and($maxHeight)->toBe('440px');
})->with([
    'por agente' => [TotalSaleForAgent::class],
    'mes vs mes anterior' => [TotalSaleMonthlyNowVsLastAgent::class],
]);

it('expone leyenda en el comparativo mensual con RawJs', function () {
    $method = new ReflectionMethod(TotalSaleMonthlyNowVsLastAgent::class, 'getOptions');
    $options = $method->invoke(new TotalSaleMonthlyNowVsLastAgent);

    expect($options)->toBeInstanceOf(RawJs::class);
    $html = $options->toHtml();
    expect($html)->toContain('display: true')
        ->and($html)->toContain("position: 'top'");
});
