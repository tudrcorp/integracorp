<?php

declare(strict_types=1);

use App\Enums\OperationInventoryProductPresentation;
use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Models\OperationInventoryProduct;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('configura el resource de productos en el grupo de inventario', function () {
    expect(OperationInventoryProductResource::getNavigationLabel())->toBe('Productos')
        ->and(OperationInventoryProductResource::getNavigationGroup())->toBe('INVENTARIO DIAGNOMOVIL')
        ->and(OperationInventoryProductResource::getNavigationSort())->toBe(3)
        ->and(OperationInventoryProductResource::getModelLabel())->toBe('Producto')
        ->and(OperationInventoryProductResource::getPluralModelLabel())->toBe('Productos');
});

it('registra el permiso de navegación productos', function () {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(
        OperationInventoryProductResource::class
    ))->toBe(['productos']);

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['operationinventoryproductresource'] ?? null)->toBe(['productos']);
});

it('configura OperationInventoryProductsTable con UX de catálogo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Tables/OperationInventoryProductsTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->heading('Productos'")
        ->toContain("->defaultSort('name'")
        ->toContain("TextColumn::make('code')")
        ->toContain("TextColumn::make('name')")
        ->toContain("TextColumn::make('category.name')")
        ->toContain("TextColumn::make('cost')")
        ->toContain("TextColumn::make('unit')")
        ->toContain("TextColumn::make('presentation')")
        ->toContain("SelectFilter::make('operation_inventory_product_category_id'")
        ->toContain("SelectFilter::make('presentation'")
        ->toContain("ViewAction::make()->label('Ver')")
        ->toContain('ExportProductsCsvBulkAction::make()')
        ->toContain('BulkAdjustProductExistenceActions::increase()')
        ->toContain('BulkAdjustProductExistenceActions::decrease()');
});

it('usa AuthorizesDepartmentNavigation en el resource de productos', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/OperationInventoryProductResource.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('AuthorizesDepartmentNavigation');
});

it('aplica estilos de afiliaciones en el formulario de productos', function () {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Schemas/OperationInventoryProductForm.php'
    );

    expect($form)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('operationInventoryProductFormTabs')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Fieldset::make('Identificación del producto')")
        ->toContain("Fieldset::make('Comercialización')")
        ->toContain("Fieldset::make('Estatus')")
        ->toContain("Select::make('is_active')")
        ->toContain("->label('Estatus')")
        ->toContain('prefixIcon')
        ->toContain('heroicon-m-cube');
});

it('define fillable y casts del modelo de productos', function () {
    $model = new OperationInventoryProduct;

    expect($model->getFillable())->toEqualCanonicalizing([
        'operation_inventory_product_category_id',
        'code',
        'name',
        'cost',
        'unit',
        'presentation',
        'is_active',
        'created_by',
    ])
        ->and($model->getCasts())->toHaveKey('is_active', 'boolean')
        ->and($model->getCasts())->toHaveKey('presentation', OperationInventoryProductPresentation::class)
        ->and($model->getCasts())->toHaveKey('cost', 'decimal:2');
});

it('expone opciones de presentación caja y unidad', function () {
    expect(OperationInventoryProductPresentation::options())->toBe([
        'CAJA' => 'Caja',
        'UNIDAD' => 'Unidad',
    ])
        ->and(OperationInventoryProductPresentation::fromStored('caja'))->toBe(OperationInventoryProductPresentation::Caja)
        ->and(OperationInventoryProductPresentation::fromStored('unidad'))->toBe(OperationInventoryProductPresentation::Unidad);
});
