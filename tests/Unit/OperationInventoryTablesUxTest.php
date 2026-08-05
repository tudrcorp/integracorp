<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;

it('ordena el menú de inventario en el orden operativo esperado', function () {
    expect([
        OperationInventoryUbicationResource::getNavigationSort() => OperationInventoryUbicationResource::getNavigationLabel(),
        OperationInventoryProductCategoryResource::getNavigationSort() => OperationInventoryProductCategoryResource::getNavigationLabel(),
        OperationInventoryProductResource::getNavigationSort() => OperationInventoryProductResource::getNavigationLabel(),
        OperationInventoryResource::getNavigationSort() => OperationInventoryResource::getNavigationLabel(),
        OperationInventoryMovementResource::getNavigationSort() => OperationInventoryMovementResource::getNavigationLabel(),
        OperationInventoryEntryResource::getNavigationSort() => OperationInventoryEntryResource::getNavigationLabel(),
        OperationInventoryOutflowResource::getNavigationSort() => OperationInventoryOutflowResource::getNavigationLabel(),
    ])->toBe([
        1 => 'Almacenes',
        2 => 'Categorías',
        3 => 'Productos',
        4 => 'Inventario General',
        5 => 'Movimientos de Inventario',
        6 => 'Entradas de Inventario',
        7 => 'Salidas de Inventario',
    ]);
});

it('estandariza la UX de las tablas del módulo de inventario', function (string $relativePath, array $expected): void {
    $path = dirname(__DIR__, 2).'/'.$relativePath;
    $contents = file_get_contents($path);

    foreach ($expected as $snippet) {
        expect($contents)->toContain($snippet);
    }
})->with([
    'inventario general' => [
        'app/Filament/Operations/Resources/OperationInventories/Tables/OperationInventoriesTable.php',
        [
            "->heading('Inventario general')",
            "->label('Código')",
            "->label('Almacén')",
            "->label('Existencia')",
            "SelectFilter::make('operation_inventory_ubication_id')",
            "ViewAction::make()->label('Ver')",
        ],
    ],
    'entradas' => [
        'app/Filament/Operations/Resources/OperationInventoryEntries/Tables/OperationInventoryEntriesTable.php',
        [
            "->heading('Entradas de inventario')",
            "->label('Código')",
            "->label('Almacén')",
            "->label('Cantidad entrante')",
            "ViewAction::make()->label('Ver')",
        ],
    ],
    'salidas' => [
        'app/Filament/Operations/Resources/OperationInventoryOutflows/Tables/OperationInventoryOutflowsTable.php',
        [
            "->heading('Salidas de inventario')",
            "->label('Código')",
            "->label('Almacén')",
            "->label('Cantidad saliente')",
            "->label('Nº caso')",
            "TextColumn::make('telemedicineCase.code')",
            "->searchPlaceholder('Buscar por código, producto, almacén, caso o tipo…')",
            "->emptyStateHeading('Sin salidas de inventario')",
            'ViewAction::make()',
        ],
    ],
    'movimientos' => [
        'app/Filament/Operations/Resources/OperationInventoryMovements/Tables/OperationInventoryMovementsTable.php',
        [
            "->heading('Movimientos de inventario')",
            "->label('Código')",
            "->label('Producto')",
            "->label('Almacén')",
            "->label('Nº caso')",
            "TextColumn::make('telemedicineCase.code')",
            "->searchPlaceholder('Buscar por código, producto, caso, paciente o tipo…')",
            "->emptyStateHeading('Sin movimientos de inventario')",
            'ViewAction::make()',
        ],
    ],
    'productos' => [
        'app/Filament/Operations/Resources/OperationInventoryProducts/Tables/OperationInventoryProductsTable.php',
        [
            "->heading('Productos')",
            "->label('Existencia total')",
            "->sum('stocks', 'existence')",
        ],
    ],
    'almacenes' => [
        'app/Filament/Operations/Resources/OperationInventoryUbications/Tables/OperationInventoryUbicationsTable.php',
        [
            "->heading('Almacenes')",
            "->counts('inventories')",
            "->label('Ítems')",
        ],
    ],
]);

it('estandariza la UX de las infolists del módulo de inventario', function (string $relativePath, array $expected): void {
    $path = dirname(__DIR__, 2).'/'.$relativePath;
    $contents = file_get_contents($path);

    foreach ($expected as $snippet) {
        expect($contents)->toContain($snippet);
    }
})->with([
    'inventario general' => [
        'app/Filament/Operations/Resources/OperationInventories/Schemas/OperationInventoryInfolist.php',
        [
            "Tabs::make('operationInventoryInfolistTabs')",
            'TABS_CONTAINER',
            'SECTION_CARD',
            "Tab::make('Resumen')",
            "Tab::make('Clasificación')",
            "Tab::make('Almacén')",
            "Tab::make('Registro')",
            "->label('Existencia')",
        ],
    ],
    'productos' => [
        'app/Filament/Operations/Resources/OperationInventoryProducts/Schemas/OperationInventoryProductInfolist.php',
        [
            "Tabs::make('operationInventoryProductInfolistTabs')",
            'TABS_CONTAINER',
            'SECTION_CARD',
            "Tab::make('Producto')",
            "Tab::make('Existencia')",
            "Section::make('Existencia por almacén'",
            "RepeatableEntry::make('stocks'",
        ],
    ],
    'entradas' => [
        'app/Filament/Operations/Resources/OperationInventoryEntries/Schemas/OperationInventoryEntryInfolist.php',
        [
            "Tabs::make('operationInventoryEntryInfolistTabs')",
            'TABS_CONTAINER',
            'SECTION_CARD',
            "Tab::make('Resumen')",
            "->label('Cantidad entrante')",
            "Tab::make('Registro')",
        ],
    ],
    'salidas' => [
        'app/Filament/Operations/Resources/OperationInventoryOutflows/Schemas/OperationInventoryOutflowInfolist.php',
        [
            "Tabs::make('operationInventoryOutflowInfolistTabs')",
            'TABS_CONTAINER',
            'SECTION_CARD',
            "Tab::make('Resumen')",
            "->label('Cantidad saliente')",
            "Tab::make('Registro')",
        ],
    ],
    'movimientos' => [
        'app/Filament/Operations/Resources/OperationInventoryMovements/Schemas/OperationInventoryMovementInfolist.php',
        [
            "Tabs::make('operationInventoryMovementInfolistTabs')",
            'TABS_CONTAINER',
            'SECTION_CARD',
            "Tab::make('Resumen')",
            "Tab::make('Telemedicina')",
            "Tab::make('Negocio')",
            "Tab::make('Registro')",
        ],
    ],
]);
