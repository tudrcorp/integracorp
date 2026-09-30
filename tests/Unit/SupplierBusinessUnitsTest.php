<?php

declare(strict_types=1);

it('no expone el campo de unidades de negocio en la gestión Integracorp del proveedor', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagement.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementForm.php');
    $tab = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementTab.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php');

    foreach ([$shared, $form, $tab, $view] as $source) {
        expect($source)->not->toContain('unidades_negocio_especificas');
    }
});

it('conserva la columna de unidades de negocio del proveedor y sus datos', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Supplier.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_29_120000_add_unidades_negocio_especificas_to_suppliers_table.php');

    expect($model)
        ->toContain("'unidades_negocio_especificas' => 'array'")
        ->and($migration)
        ->toContain("json('unidades_negocio_especificas')->nullable()");
});
