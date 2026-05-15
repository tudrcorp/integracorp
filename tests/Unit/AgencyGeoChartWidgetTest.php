<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\AgencyGeoChart;
use Filament\Support\RawJs;

it('registra chartjs-plugin-datalabels y expone porcentajes en el dataset', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Widgets/AgencyGeoChart.php';
    $code = file_get_contents($path);
    expect($code)->not->toBeFalse()
        ->and($code)->toContain('chartjs-datalabels')
        ->and($code)->toContain("'percentages'")
        ->and($code)->toContain("'agencyDetails'")
        ->and($code)->toContain('datalabels:');
});

it('incluye bloque datalabels en getOptions', function (): void {
    $widget = new AgencyGeoChart;
    $method = new ReflectionMethod(AgencyGeoChart::class, 'getOptions');
    $options = $method->invoke($widget);

    expect($options)->toBeInstanceOf(RawJs::class);
    expect($options->toHtml())->toContain('datalabels')
        ->and($options->toHtml())->toContain('percentages')
        ->and($options->toHtml())->toContain('agencyDetails')
        ->and($options->toHtml())->toContain('SIN-CODIGO')
        ->and($options->toHtml())->toContain('Total:');
});
