<?php

declare(strict_types=1);

it('muestra código, almacén y cantidad entrante en la tabla de entradas', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryEntries/Tables/OperationInventoryEntriesTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("TextColumn::make('product.code')")
        ->toContain("->label('Código')")
        ->toContain("TextColumn::make('ubication.name')")
        ->toContain("->label('Almacén')")
        ->toContain("TextColumn::make('quantity')")
        ->toContain("->label('Cantidad entrante')");
});

it('expone bulk action de exportar csv sin filtros en entradas', function () {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryEntries/Tables/OperationInventoryEntriesTable.php'
    );
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($table)
        ->toContain("BulkAction::make('export_entries_csv')")
        ->toContain("->label('Exportar CSV')")
        ->toContain('OperationInventoryEntryExportCsvController::storeIdsAndGetToken')
        ->toContain('CsvExportDownloadTrigger::fromAction');

    expect($routes)
        ->toContain("->name('operations.inventory-entries.export-csv')")
        ->toContain('OperationInventoryEntryExportCsvController::class');
});

it('carga relaciones de producto y almacén en el resource de entradas', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryEntries/OperationInventoryEntryResource.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('getEloquentQuery')
        ->toContain("'product'")
        ->toContain("'ubication'");
});
