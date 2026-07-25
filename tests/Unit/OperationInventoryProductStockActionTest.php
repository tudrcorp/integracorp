<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationInventoryProducts\Actions\LoadProductExistenceAction;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\ViewOperationInventoryProduct;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;

it('expone la acción de cargar existencia en la vista del producto', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Pages/ViewOperationInventoryProduct.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('LoadProductExistenceAction::make()')
        ->and(class_exists(ViewOperationInventoryProduct::class))->toBeTrue()
        ->and(class_exists(LoadProductExistenceAction::class))->toBeTrue();
});

it('construye campos dinámicos por cada almacén activo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Actions/LoadProductExistenceAction.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('activeUbications')
        ->toContain('TextInput::make("ubication_{$ubication->id}")')
        ->toContain('firstOrNew')
        ->toContain('existence');
});

it('define relaciones de stock en producto y almacén', function () {
    $productPath = dirname(__DIR__, 2).'/app/Models/OperationInventoryProduct.php';
    $ubicationPath = dirname(__DIR__, 2).'/app/Models/OperationInventoryUbication.php';
    $stockPath = dirname(__DIR__, 2).'/app/Models/OperationInventoryProductStock.php';

    expect(file_get_contents($productPath))->toContain('function stocks(): HasMany')
        ->and(file_get_contents($ubicationPath))->toContain('function productStocks(): HasMany')
        ->and(file_get_contents($stockPath))->toContain('function product(): BelongsTo')
        ->and(file_get_contents($stockPath))->toContain('function ubication(): BelongsTo')
        ->and((new OperationInventoryProduct)->getFillable())->not->toContain('existence')
        ->and((new OperationInventoryProductStock)->getFillable())->toContain('existence', 'operation_inventory_ubication_id');
});

it('muestra existencias por almacén en la infolist del producto', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Schemas/OperationInventoryProductInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("Section::make('Existencia por almacén'")
        ->toContain("RepeatableEntry::make('stocks'")
        ->toContain("TextEntry::make('total_existence'");
});

it('consulta solo almacenes activos para la acción', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Actions/LoadProductExistenceAction.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->where('is_active', true)")
        ->and(method_exists(LoadProductExistenceAction::class, 'activeUbications'))->toBeTrue()
        ->and(method_exists(OperationInventoryUbication::class, 'productStocks'))->toBeTrue();
});
