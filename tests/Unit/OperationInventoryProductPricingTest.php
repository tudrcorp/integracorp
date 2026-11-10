<?php

declare(strict_types=1);

use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Support\Exports\OperationInventoryProductCsvExportService;
use App\Support\Operations\OperationInventoryProductPricing;

function inventoryProductWithCategory(mixed $cost, mixed $profitPercentage): OperationInventoryProduct
{
    $category = new OperationInventoryProductCategory(['name' => 'Medicamento', 'profit_percentage' => $profitPercentage]);
    $product = new OperationInventoryProduct(['code' => 'P-001', 'name' => 'Ibuprofeno', 'cost' => $cost]);
    $product->setRelation('category', $category);
    $product->setAttribute('filtered_existence', 7);

    return $product;
}

it('calcula la utilidad sobre el costo, no sobre la venta', function (mixed $cost, mixed $percentage, ?float $expected): void {
    expect(OperationInventoryProductPricing::finalPrice($cost, $percentage))->toBe($expected);
})->with([
    'costo 100 al 30 %' => [100, 30, 130.0],
    'decimales en costo y porcentaje' => ['12.50', '15.50', 14.44],
    'utilidad cero' => [80, 0, 80.0],
    'costo cero' => [0, 30, 0.0],
    'utilidad del 100 %' => [45, 100, 90.0],
    'porcentaje sin definir' => [100, null, null],
    'costo sin definir' => [null, 30, null],
    'valor no numérico' => ['abc', 30, null],
]);

it('toma el porcentaje de la categoría del producto', function (): void {
    $product = inventoryProductWithCategory(200, 25);

    expect(OperationInventoryProductPricing::profitPercentageFor($product))->toBe(25.0)
        ->and(OperationInventoryProductPricing::finalPriceFor($product))->toBe(250.0);
});

it('no inventa precio si el producto no tiene categoría o la categoría no tiene porcentaje', function (): void {
    $sinCategoria = new OperationInventoryProduct(['cost' => 100]);
    $sinCategoria->setRelation('category', null);

    expect(OperationInventoryProductPricing::finalPriceFor($sinCategoria))->toBeNull()
        ->and(OperationInventoryProductPricing::finalPriceFor(inventoryProductWithCategory(100, null)))->toBeNull();
});

it('declara el porcentaje de utilidad en el modelo de categoría', function (): void {
    $category = new OperationInventoryProductCategory(['profit_percentage' => '30']);

    expect($category->getFillable())->toContain('profit_percentage')
        ->and($category->getCasts())->toHaveKey('profit_percentage', 'decimal:2');
});

it('escribe costo, utilidad y precio final en la fila del CSV', function (): void {
    $headers = OperationInventoryProductCsvExportService::headers();
    $row = OperationInventoryProductCsvExportService::row(inventoryProductWithCategory(100, 30), null, 'TODOS');

    expect(count($row))->toBe(count($headers))
        ->and($row[array_search('Costo', $headers, true)])->toBe('100.00')
        ->and($row[array_search('% utilidad', $headers, true)])->toBe('30.00')
        ->and($row[array_search('Precio final', $headers, true)])->toBe('130.00');
});

it('marca sin definir en el CSV cuando la categoría no tiene porcentaje', function (): void {
    $headers = OperationInventoryProductCsvExportService::headers();
    $row = OperationInventoryProductCsvExportService::row(inventoryProductWithCategory(100, null), null, 'TODOS');

    expect($row[array_search('% utilidad', $headers, true)])->toBe('Sin definir')
        ->and($row[array_search('Precio final', $headers, true)])->toBe('Sin definir');
});

it('carga el porcentaje en el eager load del CSV para no generar N+1', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/OperationInventoryProductCsvExportService.php'))
        ->toContain("->with(['category:id,name,profit_percentage'])");
});

it('muestra costo, % de utilidad y precio final en la tabla de productos', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Tables/OperationInventoryProductsTable.php');

    expect($table)
        ->toContain("TextColumn::make('cost')")
        ->toContain("TextColumn::make('category.profit_percentage')")
        ->toContain("->label('% utilidad')")
        ->toContain("TextColumn::make('final_price')")
        ->toContain("->label('Precio final')")
        ->toContain('OperationInventoryProductPricing::finalPriceFor($record)');
});

it('permite cargar el porcentaje en el formulario de categoría con validación', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProductCategories/Schemas/OperationInventoryProductCategoryForm.php');

    expect($form)
        ->toContain("TextInput::make('profit_percentage')")
        ->toContain("->label('Porcentaje de utilidad')")
        ->toContain('->minValue(0)')
        ->toContain('->maxValue(OperationInventoryProductPricing::MAX_PROFIT_PERCENTAGE)')
        ->not->toContain("TextInput::make('profit_percentage')\n                                                    ->required()");
});

it('no persiste el precio final en productos ni en documentos', function (): void {
    expect((new OperationInventoryProduct)->getFillable())->not->toContain('final_price');

    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_11_06_100000_add_profit_percentage_to_operation_inventory_product_categories_table.php');

    expect($migration)
        ->toContain("Schema::hasColumn('operation_inventory_product_categories', 'profit_percentage')")
        ->toContain("->decimal('profit_percentage', 8, 2)->nullable()")
        ->not->toContain('operation_inventory_products');
});
