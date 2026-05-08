<?php

declare(strict_types=1);

it('coloca agentes activos a la izquierda y ventas totales por estructura a la derecha en el listado de agencias', function (): void {
    $root = dirname(__DIR__, 2);

    $list = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Pages/ListAgencies.php');
    expect($list)->toContain('getHeaderWidgetsColumns')
        ->and(strpos($list, 'AgentActiveForEstructureChart::class'))->toBeLessThan(strpos($list, 'TotalEstructureAgency::class'));

    $agentChart = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Widgets/AgentActiveForEstructureChart.php');
    $ventasEstructura = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Widgets/TotalEstructureAgency.php');
    expect($agentChart)->toContain('protected int|string|array $columnSpan = 1')
        ->and($ventasEstructura)->toContain('protected int|string|array $columnSpan = 1');
});

it('homologa el alto de Agentes activos por agencia agregando una descripción equivalente a Ventas totales por estructura', function (): void {
    $root = dirname(__DIR__, 2);

    $agentChart = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Widgets/AgentActiveForEstructureChart.php');
    $ventasEstructura = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Widgets/TotalEstructureAgency.php');

    expect($agentChart)
        ->toContain('protected ?string $description =')
        ->toContain('Ajusta año y mes')
        ->and($ventasEstructura)->toContain('protected ?string $description =');

    expect($agentChart)->toContain("protected ?string \$maxHeight = '440px'")
        ->and($ventasEstructura)->toContain("protected ?string \$maxHeight = '440px'");
});

it('coloca el filtro de Ventas totales por estructura encima del título', function (): void {
    $root = dirname(__DIR__, 2);

    $blade = file_get_contents($root.'/resources/views/filament/widgets/total-estructure-agency-chart.blade.php');

    expect($blade)
        ->not->toContain('<x-slot name="afterHeader">')
        ->toContain('fi-total-estructure-agency-chart-toolbar')
        ->toContain('fi-total-estructure-agency-chart-header');

    $toolbarPos = strpos($blade, 'fi-total-estructure-agency-chart-toolbar');
    $headerPos = strpos($blade, 'fi-total-estructure-agency-chart-header');

    expect($toolbarPos)->toBeLessThan($headerPos);
});
