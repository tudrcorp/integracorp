<?php

declare(strict_types=1);

it('muestra totales usd y ves bajo el título sin widgets de stats en comisiones', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Commissions/Pages/ListCommissions.php';
    $source = file_get_contents($listPath);

    expect($source)
        ->toContain('getSubheading')
        ->toContain('Comisiones totales USD')
        ->toContain('Comisiones totales VES')
        ->toContain('rounded-2xl')
        ->toContain('statIcon')
        ->not->toContain('getHeaderWidgets')
        ->not->toContain('StatsOverviewCommissionUsdVes')
        ->not->toContain('ExposesTableToWidgets');
});
