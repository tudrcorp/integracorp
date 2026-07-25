<?php

declare(strict_types=1);

it('muestra código, almacén y cantidad saliente en la tabla de salidas', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Tables/OperationInventoryOutflowsTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("TextColumn::make('product.code')")
        ->toContain("->label('Código')")
        ->toContain("TextColumn::make('ubication.name')")
        ->toContain("->label('Almacén')")
        ->toContain("TextColumn::make('quantity')")
        ->toContain("->label('Cantidad saliente')");
});

it('expone bulk action de exportar csv sin filtros en salidas', function () {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Tables/OperationInventoryOutflowsTable.php'
    );
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($table)
        ->toContain("BulkAction::make('export_outflows_csv')")
        ->toContain("->label('Exportar CSV')")
        ->toContain('OperationInventoryOutflowExportCsvController::storeIdsAndGetToken')
        ->toContain('CsvExportDownloadTrigger::fromAction');

    expect($routes)
        ->toContain("->name('operations.inventory-outflows.export-csv')")
        ->toContain('OperationInventoryOutflowExportCsvController::class');
});

it('carga relaciones de producto y almacén en el resource de salidas', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/OperationInventoryOutflowResource.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('getEloquentQuery')
        ->toContain("'product'")
        ->toContain("'ubication'");
});
