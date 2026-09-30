<?php

declare(strict_types=1);

use App\Support\Operations\OperationInventoryListHeaders;

uses(Tests\TestCase::class);

/**
 * Solo lee: los encabezados cuentan con una consulta agregada, sin escribir.
 */
it('cada listado de inventario muestra su encabezado con título y resumen', function (string $method, string $title, array $chips): void {
    $html = (string) OperationInventoryListHeaders::{$method}();

    expect($html)
        ->toContain('Inventario Diagnomóvil')
        ->toContain($title);

    foreach ($chips as $chip) {
        expect($html)->toContain($chip);
    }
})->with([
    'almacenes' => ['warehouses', 'Almacenes', ['Activos', 'Con existencia']],
    'categorías' => ['categories', 'Categorías', ['Activas', 'Sin productos']],
    'productos' => ['products', 'Productos', ['Stock bajo', 'Sin existencia']],
    'inventario general' => ['inventory', 'Inventario general', ['Stock bajo', 'Almacenes']],
    'movimientos' => ['movements', 'Movimientos de inventario', ['Medicamentos', 'Casos atendidos']],
    'entradas' => ['entries', 'Entradas de inventario', ['Reposiciones']],
    'salidas' => ['outflows', 'Salidas de inventario', ['Telemedicina', 'Ajustes']],
]);

it('los chips solo se muestran cuando hay registros, pero parámetros no lleva total', function (): void {
    $html = (string) OperationInventoryListHeaders::parameters();

    expect($html)
        ->toContain('Parámetros de inventario')
        ->toContain('umbral de stock bajo')
        ->not->toContain('aria-label="Resumen"');
});

it('las páginas del inventario usan el encabezado compartido', function (string $file, string $method): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/'.$file);

    expect($source)
        ->toContain('public function getHeading(): string|Htmlable')
        ->toContain('OperationInventoryListHeaders::'.$method.'()')
        ->not->toContain('function getSubheading');
})->with([
    ['Resources/OperationInventoryUbications/Pages/ListOperationInventoryUbications.php', 'warehouses'],
    ['Resources/OperationInventoryProductCategories/Pages/ListOperationInventoryProductCategories.php', 'categories'],
    ['Resources/OperationInventoryProducts/Pages/ListOperationInventoryProducts.php', 'products'],
    ['Resources/OperationInventories/Pages/ListOperationInventories.php', 'inventory'],
    ['Resources/OperationInventoryMovements/Pages/ListOperationInventoryMovements.php', 'movements'],
    ['Resources/OperationInventoryEntries/Pages/ListOperationInventoryEntries.php', 'entries'],
    ['Resources/OperationInventoryOutflows/Pages/ListOperationInventoryOutflows.php', 'outflows'],
    ['Pages/ManageOperationInventoryParameters.php', 'parameters'],
]);
