<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierStatsOverviewFirts;

it('define el widget de resumen de convenios de proveedores', function () {
    expect(class_exists(SupplierStatsOverviewFirts::class))->toBeTrue()
        ->and(is_subclass_of(SupplierStatsOverviewFirts::class, \Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
});

it('muestra total general y registrados del mes actual en dos columnas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierStatsOverviewFirts.php');

    expect($source)
        ->toContain("Stat::make('PROVEEDORES REGISTRADOS'")
        ->toContain("Stat::make('REGISTRADOS EN EL MES'")
        ->toContain('->whereBetween("{$table}.created_at"')
        ->toContain('MES ACTUAL ·')
        ->toContain('getColumns()');
});
