<?php

declare(strict_types=1);

it('alinea AgentActiveForEstructureChart con el estilo SupplierClasificationChart y conserva la paleta', function (): void {
    $root = dirname(__DIR__, 2);
    $widgetPath = $root.'/app/Filament/Business/Resources/Agencies/Widgets/AgentActiveForEstructureChart.php';
    $bladePath = $root.'/resources/views/filament/widgets/agent-active-for-estructure-chart.blade.php';

    $widget = file_get_contents($widgetPath);
    expect($widget)
        ->toContain('agent-active-for-estructure-chart')
        ->and($widget)->toContain("protected ?string \$maxHeight = '440px'")
        ->and($widget)->toContain("protected string \$color = 'gray'")
        ->and($widget)->toContain('#22c55e')
        ->and($widget)->toContain('#4ade80')
        ->and($widget)->toContain('easeOutQuart')
        ->and($widget)->toContain('rgba(22, 22, 24, 0.56)')
        ->and($widget)->toContain('categoryPercentage: 0.92')
        ->and($widget)->toContain('detalles de activos/inactivos');

    $blade = file_get_contents($bladePath);
    expect($blade)
        ->toContain('getMaxHeight()')
        ->and($blade)->toContain('fi-agency-registrations-chart-like-suppliers')
        ->and($blade)->toContain('agent-active-estructure-');
});
