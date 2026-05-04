<?php

declare(strict_types=1);

use App\Filament\Business\Widgets\StatsOverview;
use App\Filament\Business\Widgets\StatsOverviewSaleBusiness;
use App\Filament\Business\Widgets\StatsOverviewSaleUsdVesBusiness;
use App\Filament\Business\Widgets\TwoStatsOverview;

it('renders business stats descriptions with larger typography', function (): void {
    $overviewMethod = new ReflectionMethod(StatsOverview::class, 'descriptionHtml');
    $overviewMethod->setAccessible(true);

    $overviewHtml = $overviewMethod->invoke(
        null,
        2026,
        12,
        'Abril',
        'text-info-600 dark:text-info-400',
        'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
    )->toHtml();

    expect($overviewHtml)
        ->toContain('text-sm font-semibold uppercase tracking-wide')
        ->toContain('px-2.5 py-1 text-sm font-bold')
        ->toContain('text-base font-bold text-gray-900');

    $twoStatsMethod = new ReflectionMethod(TwoStatsOverview::class, 'descriptionHtml');
    $twoStatsMethod->setAccessible(true);

    $twoStatsHtml = $twoStatsMethod->invoke(
        null,
        2026,
        7,
        'Abril',
        'text-success-600 dark:text-success-400',
        'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
    )->toHtml();

    expect($twoStatsHtml)
        ->toContain('text-sm font-semibold uppercase tracking-wide')
        ->toContain('px-2.5 py-1 text-sm font-bold')
        ->toContain('text-base font-bold text-gray-900');

    $saleMethod = new ReflectionMethod(StatsOverviewSaleBusiness::class, 'descriptionHtml');
    $saleMethod->setAccessible(true);

    $saleHtml = $saleMethod->invoke(
        null,
        2026,
        'Abril',
        'US$ 1.234,00',
        'text-primary-600 dark:text-primary-400',
        'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
    )->toHtml();

    expect($saleHtml)
        ->toContain('text-sm font-semibold uppercase tracking-wide')
        ->toContain('px-2.5 py-1 text-sm font-bold')
        ->toContain('text-base font-bold text-gray-900');

    $saleUsdVesMethod = new ReflectionMethod(StatsOverviewSaleUsdVesBusiness::class, 'descriptionHtml');
    $saleUsdVesMethod->setAccessible(true);

    $saleUsdVesHtml = $saleUsdVesMethod->invoke(
        null,
        2026,
        'Abril',
        'Bs. 9.999,00',
        'text-warning-600 dark:text-warning-400',
        'bg-warning-100/90 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
    )->toHtml();

    expect($saleUsdVesHtml)
        ->toContain('text-sm font-semibold uppercase tracking-wide')
        ->toContain('px-2.5 py-1 text-sm font-bold')
        ->toContain('text-base font-bold text-gray-900');
});
