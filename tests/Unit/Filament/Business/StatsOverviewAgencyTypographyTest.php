<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\StatsOverviewAgency;

it('renders agency stats description with larger typography', function (): void {
    $monthlyMethod = new ReflectionMethod(StatsOverviewAgency::class, 'monthlyGrowthDescription');
    $monthlyMethod->setAccessible(true);

    $monthlyHtml = $monthlyMethod->invoke(
        null,
        '2026',
        25,
        'Abril',
        2,
        'text-info-600 dark:text-info-400',
        'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
        'master',
    )->toHtml();

    expect($monthlyHtml)
        ->toContain('text-sm font-medium uppercase tracking-wide')
        ->toContain('tabular-nums text-base font-medium')
        ->toContain('Mes seleccionado')
        ->toContain('text-sm font-medium text-gray-500');

    $distributionMethod = new ReflectionMethod(StatsOverviewAgency::class, 'totalAgenciesDistributionDescription');
    $distributionMethod->setAccessible(true);

    $distributionHtml = $distributionMethod->invoke(
        null,
        '2026',
        5,
        'Abril',
        5,
        0,
        2,
        0,
    )->toHtml();

    expect($distributionHtml)
        ->toContain('text-sm font-medium uppercase tracking-wide')
        ->toContain('tabular-nums text-base font-medium')
        ->toContain('Mes seleccionado')
        ->toContain('text-sm font-medium text-gray-500');
});
