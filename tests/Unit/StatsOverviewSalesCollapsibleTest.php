<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Sales\Widgets\StatsOverviewSales;
use App\Filament\Administration\Resources\Sales\Widgets\StatsOverviewSalesUsdVes;

it('colapsa por defecto los stats del recurso de ventas sin alpine', function (): void {
    $planStats = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/StatsOverviewSales.php');
    $incomeStats = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/StatsOverviewSalesUsdVes.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Widgets/Concerns/HasCollapsibleSalesStatsPanel.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/administration/widgets/sales-collapsible-stats-overview.blade.php');
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $listSales = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ListSales.php');

    expect($planStats)
        ->toContain('HasCollapsibleSalesStatsPanel')
        ->toContain("return 'planes'")
        ->and($incomeStats)
        ->toContain('HasCollapsibleSalesStatsPanel')
        ->toContain("return 'ingresos'")
        ->and($trait)
        ->toContain('sectionExpanded = false')
        ->toContain('toggleSection')
        ->and($planStats)
        ->toContain('sales-collapsible-stats-overview')
        ->and($incomeStats)
        ->toContain('sales-collapsible-stats-overview')
        ->and($view)
        ->toContain('wire:click="toggleSection"')
        ->toContain('@if ($expanded)')
        ->and($theme)
        ->toContain('.fi-admin-sales-stats-panel')
        ->and($listSales)
        ->toContain('StatsOverviewSalesUsdVes::class')
        ->toContain('StatsOverviewSales::class');
});

it('inicia colapsado y permite alternar el panel', function (): void {
    $ingresos = app(StatsOverviewSalesUsdVes::class);
    $planes = app(StatsOverviewSales::class);

    expect($ingresos->sectionExpanded)->toBeFalse()
        ->and($planes->sectionExpanded)->toBeFalse();

    $ingresos->toggleSection();
    $planes->toggleSection();

    expect($ingresos->sectionExpanded)->toBeTrue()
        ->and($planes->sectionExpanded)->toBeTrue();

    $ingresos->toggleSection();

    expect($ingresos->sectionExpanded)->toBeFalse();
});
