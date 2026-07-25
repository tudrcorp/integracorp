<?php

declare(strict_types=1);

use App\Services\OperationInventoryProductCsvImporter;

it('normaliza una fila del csv con costo cero activo y created_by INTEGRACORP', function () {
    $importer = new OperationInventoryProductCsvImporter;
    $seen = [];
    $duplicates = [];

    $mapped = $importer->normalizeRow([
        'producto' => ' ACETAMINOFEN (650MG)10 TABLETAS ',
        'presentacion' => 'CAJA ',
        'codigo' => '7592454001243',
        'categoria_id' => '1',
    ], $seen, $duplicates);

    expect($mapped)->toMatchArray([
        'operation_inventory_product_category_id' => 1,
        'code' => '7592454001243',
        'name' => 'ACETAMINOFEN (650MG)10 TABLETAS',
        'cost' => '0.00',
        'unit' => 'UNIDAD',
        'presentation' => 'CAJA',
        'is_active' => true,
        'created_by' => 'INTEGRACORP',
    ]);
});

it('genera sufijo para códigos duplicados en el csv', function () {
    $importer = new OperationInventoryProductCsvImporter;
    $seen = [];
    $duplicates = [];

    $first = $importer->normalizeRow([
        'producto' => 'TERMOMETRO DIGITAL',
        'presentacion' => 'UNIDAD',
        'codigo' => '000115',
        'categoria_id' => '1',
    ], $seen, $duplicates);

    $second = $importer->normalizeRow([
        'producto' => 'PULMO-AIDE',
        'presentacion' => 'UNIDAD',
        'codigo' => '000115',
        'categoria_id' => '1',
    ], $seen, $duplicates);

    expect($first['code'])->toBe('000115')
        ->and($second['code'])->toBe('000115-2')
        ->and($duplicates)->toBe(['000115 → 000115-2']);
});

it('incluye el csv de productos en database/data', function () {
    $path = dirname(__DIR__, 2).'/database/data/operation_inventory_products.csv';

    expect(is_file($path))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)->toContain('producto,presentacion,codigo,categoria_id')
        ->toContain('ALCOHOL ANTISEPTICO');
});
