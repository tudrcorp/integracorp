<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Sales\Widgets\CollapsibleSalesChartsWidget;
use App\Filament\Administration\Resources\Sales\Widgets\SalePlanChart;
use App\Filament\Administration\Resources\Sales\Widgets\SaleYearChart;
use App\Filament\Administration\Widgets\DashboardCollapsibleSalesChartsWidget;
use App\Filament\Administration\Widgets\DashboardSalePlanChart;
use App\Filament\Administration\Widgets\DashboardSaleYearChart;

it('colapsa por defecto los graficos de ventas con un header visible', function (): void {
    $listWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/CollapsibleSalesChartsWidget.php');
    $dashboardWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/DashboardCollapsibleSalesChartsWidget.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/Concerns/HasCollapsibleSalesChartsPanel.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/administration/widgets/sales-collapsible-charts-overview.blade.php');
    $listSales = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ListSales.php');
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $yearChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/DashboardSaleYearChart.php');
    $planChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/DashboardSalePlanChart.php');

    expect($listWidget)
        ->toContain('HasCollapsibleSalesChartsPanel')
        ->toContain('SaleYearChart::class')
        ->toContain('SalePlanChart::class')
        ->toContain('sales-collapsible-charts-overview')
        ->and($dashboardWidget)
        ->toContain('HasCollapsibleSalesChartsPanel')
        ->toContain('DashboardSaleYearChart::class')
        ->toContain('DashboardSalePlanChart::class')
        ->toContain('$sort = 1')
        ->and($trait)
        ->toContain('sectionExpanded = false')
        ->toContain('toggleSection')
        ->toContain('GRÁFICOS DE VENTAS')
        ->and($view)
        ->toContain('wire:click="toggleSection"')
        ->toContain('@if ($expanded)')
        ->toContain('Colapsado · haz clic para ver los gráficos')
        ->toContain('@livewire($yearChartWidget')
        ->toContain('@livewire($planChartWidget')
        ->and($listSales)
        ->toContain('CollapsibleSalesChartsWidget::class')
        ->not->toContain('SaleYearChart::class')
        ->not->toContain('SalePlanChart::class')
        ->and($theme)
        ->toContain('.fi-admin-sales-stats-widget--graficos')
        ->toContain('.fi-admin-sales-charts-grid')
        ->and($yearChart)
        ->toContain('$isDiscovered = false')
        ->and($planChart)
        ->toContain('$isDiscovered = false');
});

it('inicia colapsado y permite alternar el panel de graficos', function (): void {
    $list = new CollapsibleSalesChartsWidget;
    $dashboard = new DashboardCollapsibleSalesChartsWidget;

    expect($list->sectionExpanded)->toBeFalse()
        ->and($dashboard->sectionExpanded)->toBeFalse()
        ->and($list->getHeading())->toBe('GRÁFICOS DE VENTAS')
        ->and($list->yearChartWidget())->toBe(SaleYearChart::class)
        ->and($list->planChartWidget())->toBe(SalePlanChart::class)
        ->and($dashboard->yearChartWidget())->toBe(DashboardSaleYearChart::class)
        ->and($dashboard->planChartWidget())->toBe(DashboardSalePlanChart::class);

    $list->toggleSection();
    $dashboard->toggleSection();

    expect($list->sectionExpanded)->toBeTrue()
        ->and($dashboard->sectionExpanded)->toBeTrue();

    $list->toggleSection();

    expect($list->sectionExpanded)->toBeFalse();
});
