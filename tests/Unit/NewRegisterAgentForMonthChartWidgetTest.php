<?php

declare(strict_types=1);

it('define gráfico de barras por mes alineado con agencias (440px, tooltip proveedor)', function (): void {
    $root = dirname(__DIR__, 2);

    $widget = file_get_contents($root.'/app/Filament/Business/Resources/Agents/Widgets/NewRegisterAgentForMountChart.php');
    expect($widget)
        ->toContain('new-register-agent-month-chart')
        ->and($widget)->toContain("return 'bar'")
        ->and($widget)->toContain('countAgentsRegisteredForMonth')
        ->and($widget)->toContain('function getFilters')
        ->and($widget)->toContain('function updatedFilter')
        ->and($widget)->toContain('getRegistrationsTotalInCurrentView')
        ->and($widget)->toContain('getEmptyRegistrationsMessage')
        ->and($widget)->toContain('function getOptions(): RawJs')
        ->and($widget)->toContain('maintainAspectRatio: false')
        ->and($widget)->toContain('rgba(22, 22, 24, 0.56)')
        ->and($widget)->toContain("protected ?string \$maxHeight = '440px'");

    $blade = file_get_contents($root.'/resources/views/filament/widgets/new-register-agent-month-chart.blade.php');
    expect($blade)
        ->toContain('fi-agent-charts-like-suppliers')
        ->and($blade)->toContain('getRegistrationsTotalInCurrentView')
        ->and($blade)->toContain('getEmptyRegistrationsMessage')
        ->and($blade)->toContain('min-h-[360px]')
        ->and($blade)->toContain("getMaxHeight() ?? '440px'");

    expect($widget)->toContain('No hay agentes registrados en');

    $css = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($css)
        ->toContain('.fi-agent-charts-like-suppliers .fi-wi-chart-canvas-ctn canvas')
        ->and($css)->toContain('span.fi-wi-chart-text-color')
        ->and($css)->toContain('Contraste reforzado');

    $list = file_get_contents($root.'/app/Filament/Business/Resources/Agents/Pages/ListAgents.php');
    expect($list)->toContain('NewRegisterAgentForMountChart::class');
});
