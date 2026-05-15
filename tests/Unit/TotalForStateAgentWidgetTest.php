<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\TotalForStateAgent;

it('es un ChartWidget con vista dedicada para barras por estado', function () {
    expect(class_exists(TotalForStateAgent::class))->toBeTrue()
        ->and(is_subclass_of(TotalForStateAgent::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();

    $view = (new ReflectionClass(TotalForStateAgent::class))->getDefaultProperties()['view'] ?? null;

    expect($view)->toBe('filament.widgets.total-for-state-agent-chart');

    $maxHeight = (new ReflectionClass(TotalForStateAgent::class))->getDefaultProperties()['maxHeight'] ?? null;
    expect($maxHeight)->toBe('440px');
});

it('calcula el total de agentes desde el dataset en caché', function (array $cached, int $expected) {
    $widget = new TotalForStateAgent;
    $prop = (new ReflectionClass($widget))->getProperty('cachedData');
    $prop->setAccessible(true);
    $prop->setValue($widget, $cached);

    expect($widget->getAgentsTotalInCurrentView())->toBe($expected);
})->with([
    'vacío' => [
        [
            'labels' => [],
            'datasets' => [
                [
                    'data' => [],
                ],
            ],
        ],
        0,
    ],
    'con valores' => [
        [
            'labels' => ['A', 'B'],
            'datasets' => [
                [
                    'data' => [3, 7],
                ],
            ],
        ],
        10,
    ],
]);

it('expone mensaje de estado vacío', function () {
    $widget = new TotalForStateAgent;

    expect($widget->getEmptyStateMessage())->not->toBeEmpty();
});

it('serializa opciones RawJs sin comillas dobles para no romper x-data en HTML', function () {
    $widget = new TotalForStateAgent;
    $method = new ReflectionMethod(TotalForStateAgent::class, 'getOptions');
    $options = $method->invoke($widget);
    $raw = $options->toHtml();

    expect($raw)->not->toContain('"')
        ->and($raw)->toContain('agentDetails')
        ->and($raw)->toContain('${code} - ${name}')
        ->and($raw)->toContain('Total:');
});

it('genera clave estable para wire:key sin depender del estado reactivo de tabla', function () {
    $widget = new TotalForStateAgent;
    $a = $widget->getStateDistributionChartWireKey();
    $b = $widget->getStateDistributionChartWireKey();

    expect($a)->toBe($b)
        ->and($a)->toStartWith('agent-state-bar-')
        ->and(strlen($a))->toBeGreaterThan(16);
});
