<?php

declare(strict_types=1);

it('expone los graficos anuales y por plan en el dashboard de administracion', function (): void {
    $yearChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/DashboardSaleYearChart.php');
    $planChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/DashboardSalePlanChart.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php');
    $sourceYear = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/SaleYearChart.php');
    $sourcePlan = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/SalePlanChart.php');

    $agenciesChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/TotalSaleMonthlyNowVsLastAgency.php');

    expect($yearChart)
        ->toContain('extends SaleYearChart')
        ->toContain("'lg' => 1")
        ->toContain('$sort = 1')
        ->and($planChart)
        ->toContain("return 'doughnut';")
        ->toContain('DISTRIBUCIÓN DE VENTAS POR PLAN')
        ->toContain('Sale::query()')
        ->toContain("'lg' => 1")
        ->toContain('$sort = 2')
        ->and($agenciesChart)
        ->toContain('$sort = 3')
        ->and($provider)
        ->toContain("discoverWidgets(in: app_path('Filament/Administration/Widgets')")
        ->not->toContain('FilamentInfoWidget')
        ->and($sourceYear)
        ->toContain('RESUMEN DE VENTAS ANUAL')
        ->and($sourcePlan)
        ->toContain('DISTRIBUCIÓN DE VENTAS POR PLAN');
});
