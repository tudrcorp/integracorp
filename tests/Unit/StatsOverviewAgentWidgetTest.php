<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\StatsOverviewAgent;

it('calcula el porcentaje YoY (variación) y maneja división por cero', function (): void {
    $method = new ReflectionMethod(StatsOverviewAgent::class, 'calculateYearOverYearPercentChange');
    $method->setAccessible(true);

    expect($method->invoke(null, 120, 100))->toBe(20.0)
        ->and($method->invoke(null, 80, 100))->toBe(-20.0)
        ->and($method->invoke(null, 10, 0))->toBeNull();
});

it('declara estilos para sparkline de fondo en el tema', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)->not->toBeFalse()
        ->and($css)->toContain('.fi-agent-subagent-yoy-spark .fi-wi-stats-overview-stat-chart');
});
