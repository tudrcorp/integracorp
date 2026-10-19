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

it('cuenta proveedores generales y preferenciales con carta de aceptación cargada', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierStatsOverviewFirts.php');

    expect($source)
        ->toContain("Stat::make('CON CARTA DE ACEPTACIÓN'")
        ->toContain('$query->where("{$table}.status_convenio", \'GENERAL\')')
        ->toContain('->orWhere("{$table}.status_convenio", \'like\', \'%PREFERENCIAL%\')')
        ->toContain('->whereNotNull("{$table}.carta_acceptance")')
        ->toContain('->where("{$table}.carta_acceptance", \'<>\', \'\')')
        ->toContain('GENERALES + PREFERENCIALES');
});

it('cuenta los proveedores no convenidos registrados desde operaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierStatsOverviewFirts.php');

    expect($source)
        ->toContain("Stat::make('PROVEEDORES NO CONVENIDOS'")
        ->toContain('->where("{$table}.status_convenio", \'NO CONVENIDO\')')
        ->toContain('REGISTRADOS DESDE OPERACIONES');
});

it('agrupa el filtro de convenio para que el OR no escape a los filtros del listado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierStatsOverviewFirts.php');

    expect($source)->toContain('->where(function (Builder $query) use ($table): void {');
});
