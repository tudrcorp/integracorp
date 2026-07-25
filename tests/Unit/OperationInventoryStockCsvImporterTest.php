<?php

declare(strict_types=1);

use App\Services\OperationInventoryStockCsvImporter;

it('mapea almacén 1 a DIAGNOMOVIL y almacén 2 a 3 DE FEBRERO', function () {
    expect(OperationInventoryStockCsvImporter::WAREHOUSE_ONE_NAME)->toBe('DIAGNOMOVIL')
        ->and(OperationInventoryStockCsvImporter::WAREHOUSE_TWO_NAME)->toBe('3 DE FEBRERO');
});

it('incluye relaciones de producto y almacén en inventarios entradas y salidas', function () {
    $inventory = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventory.php');
    $entry = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventoryEntry.php');
    $outflow = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventoryOutflow.php');
    $product = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventoryProduct.php');

    expect($inventory)->toContain('function product(): BelongsTo')
        ->toContain('function ubicationRelation(): BelongsTo')
        ->and($entry)->toContain('function product(): BelongsTo')
        ->toContain('function ubication(): BelongsTo')
        ->and($outflow)->toContain('function product(): BelongsTo')
        ->toContain('function ubication(): BelongsTo')
        ->and($product)->toContain('function inventories(): HasMany')
        ->toContain('function inventoryEntries(): HasMany')
        ->toContain('function inventoryOutflows(): HasMany');
});

it('incluye el csv de existencias por almacén en database/data', function () {
    $path = dirname(__DIR__, 2).'/database/data/operation_inventory_stock_by_warehouse.csv';

    expect(is_file($path))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)->toContain('CODIGO')
        ->toContain('ALAMACEN 1')
        ->toContain('ALAMACEN 2')
        ->toContain('7591472327694');
});

it('define el comando de importación de stock por almacén', function () {
    $path = dirname(__DIR__, 2).'/app/Console/Commands/ImportOperationInventoryStockFromCsvCommand.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('operation-inventory-stock:import-csv')
        ->toContain('OperationInventoryStockCsvImporter');
});
