<?php

declare(strict_types=1);

it('registra widgets de afiliaciones en el dashboard de operaciones', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php';
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain('StatsOverview::class')
        ->toContain('StatsOverviewPlan::class')
        ->toContain('AffiliationChart::class')
        ->toContain('TotalAfiliacionesPorEstado::class');
});

it('widgets de afiliaciones en operaciones usan polling para refresco en tiempo real', function (): void {
    $widgets = [
        dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/StatsOverview.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/StatsOverviewPlan.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/AffiliationChart.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/TotalAfiliacionesPorEstado.php',
    ];

    foreach ($widgets as $widgetPath) {
        $contents = file_get_contents($widgetPath);
        expect($contents)->toContain("protected ?string \$pollingInterval = '10s';");
    }
});
