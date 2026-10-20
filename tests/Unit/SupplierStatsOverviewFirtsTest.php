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
        ->toContain('SupplierListStatCounts::summary($base')
        ->toContain('MES ACTUAL ·')
        ->toContain('getColumns()');
});

it('cuenta las cuatro tarjetas superiores en una sola consulta', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierStatsOverviewFirts.php');
    $counter = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/SupplierListStatCounts.php');

    expect($source)
        ->toContain("Stat::make('CON CARTA DE ACEPTACIÓN'")
        ->toContain("Stat::make('PROVEEDORES NO CONVENIDOS'")
        ->toContain('GENERALES + PREFERENCIALES')
        ->toContain('REGISTRADOS DESDE OPERACIONES')
        ->not->toContain('->count()')
        ->and($counter)
        ->toContain("(suppliers.status_convenio = 'GENERAL' OR suppliers.status_convenio LIKE '%PREFERENCIAL%')")
        ->toContain("suppliers.carta_acceptance IS NOT NULL AND suppliers.carta_acceptance <> ''")
        ->toContain("suppliers.status_convenio = 'NO CONVENIDO'")
        ->toContain('suppliers.created_at BETWEEN ? AND ?');
});
