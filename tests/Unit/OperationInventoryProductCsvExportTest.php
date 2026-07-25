<?php

declare(strict_types=1);

use App\Http\Controllers\OperationInventoryProductExportCsvController;
use App\Support\Exports\OperationInventoryProductCsvExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

it('expone la bulk action de exportar productos csv en la tabla', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Tables/OperationInventoryProductsTable.php'
    );
    $action = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Actions/ExportProductsCsvBulkAction.php'
    );

    expect($table)->toContain('ExportProductsCsvBulkAction::make()');

    expect($action)
        ->toContain("BulkAction::make('export_products_csv')")
        ->toContain("->label('Exportar CSV')")
        ->toContain("Select::make('category_id')")
        ->toContain("Select::make('ubication_id')")
        ->toContain("Select::make('existence_operator')")
        ->toContain("TextInput::make('existence_value')")
        ->toContain('OperationInventoryProductExportCsvController::storeFiltersAndGetToken')
        ->toContain('CsvExportDownloadTrigger::fromAction');
});

it('define encabezados y operadores opcionales de existencia', function (): void {
    expect(OperationInventoryProductCsvExportService::headers())
        ->toContain('Código', 'Categoría', 'Almacén', 'Existencia')
        ->and(count(OperationInventoryProductCsvExportService::headers()))->toBe(11);

    expect(OperationInventoryProductCsvExportService::existenceOperatorOptions())
        ->toBe([
            'gt' => 'Mayor a',
            'lt' => 'Menor a',
        ]);
});

it('registra la ruta de exportación de productos de inventario', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/routes/web.php'))
        ->toContain("->name('operations.inventory-products.export-csv')")
        ->toContain('OperationInventoryProductExportCsvController::class');
});

it('almacena filtros opcionales en cache y permite exportar sin filtros', function (): void {
    $token = OperationInventoryProductExportCsvController::storeFiltersAndGetToken([]);

    expect($token)->toBeString()->not->toBeEmpty();

    $cached = Cache::get('operation_inventory_product_export_csv_'.$token);

    expect($cached)->toBe([
        'category_id' => null,
        'ubication_id' => null,
        'existence_operator' => null,
        'existence_value' => null,
    ]);

    $tokenFiltered = OperationInventoryProductExportCsvController::storeFiltersAndGetToken([
        'category_id' => 3,
        'ubication_id' => 2,
        'existence_operator' => 'gt',
        'existence_value' => 10,
    ]);

    expect(Cache::get('operation_inventory_product_export_csv_'.$tokenFiltered))->toBe([
        'category_id' => 3,
        'ubication_id' => 2,
        'existence_operator' => 'gt',
        'existence_value' => 10,
    ]);
});

it('responde csv con token de exportación', function (): void {
    $token = OperationInventoryProductExportCsvController::storeFiltersAndGetToken([
        'existence_operator' => 'gt',
        'existence_value' => 0,
    ]);

    $request = Request::create('/operations/export-inventory-products-csv', 'GET', [
        'token' => $token,
    ]);

    $response = app(OperationInventoryProductExportCsvController::class)($request);

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('.csv');
});

it('aplica filtros opcionales en la consulta de exportación', function (): void {
    $service = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Exports/OperationInventoryProductCsvExportService.php'
    );

    expect($service)
        ->toContain("->where('operation_inventory_product_category_id', \$categoryId)")
        ->toContain("->where('operation_inventory_ubication_id', \$ubicationId)")
        ->toContain("\$comparison = \$operator === 'gt' ? '>' : '<'")
        ->toContain('withSum');
});
