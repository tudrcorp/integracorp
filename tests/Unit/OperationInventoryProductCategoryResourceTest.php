<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use Database\Seeders\OperationInventoryProductCategorySeeder;

it('configura el resource de categorías en el grupo de inventario', function () {
    expect(OperationInventoryProductCategoryResource::getNavigationLabel())->toBe('Categorías')
        ->and(OperationInventoryProductCategoryResource::getNavigationGroup())->toBe('INVENTARIO DIAGNOMOVIL')
        ->and(OperationInventoryProductCategoryResource::getNavigationSort())->toBe(2)
        ->and(OperationInventoryProductCategoryResource::getModelLabel())->toBe('Categoría')
        ->and(OperationInventoryProductCategoryResource::getPluralModelLabel())->toBe('Categorías');
});

it('registra el permiso de navegación categorias', function () {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(
        OperationInventoryProductCategoryResource::class
    ))->toBe(['categorias']);

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['operationinventoryproductcategoryresource'] ?? null)->toBe(['categorias']);
});

it('configura OperationInventoryProductCategoriesTable con UX de catálogo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProductCategories/Tables/OperationInventoryProductCategoriesTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->heading('Categorías'")
        ->toContain("->defaultSort('name'")
        ->toContain("TextColumn::make('name')")
        ->toContain("->counts('products')")
        ->toContain("ViewAction::make()->label('Ver')");
});

it('aplica estilos del sistema en el formulario de categorías', function () {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProductCategories/Schemas/OperationInventoryProductCategoryForm.php'
    );

    expect($form)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('operationInventoryProductCategoryFormTabs')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Fieldset::make('Identificación de la categoría')")
        ->toContain("Fieldset::make('Estatus')")
        ->toContain("Select::make('is_active')")
        ->toContain("->label('Estatus')")
        ->toContain('prefixIcon')
        ->toContain('heroicon-m-tag');
});

it('usa AuthorizesDepartmentNavigation en el resource de categorías', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProductCategories/OperationInventoryProductCategoryResource.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('AuthorizesDepartmentNavigation');
});

it('define fillable, cast y relación del modelo de categorías', function () {
    $model = new OperationInventoryProductCategory;
    $path = dirname(__DIR__, 2).'/app/Models/OperationInventoryProductCategory.php';
    $contents = file_get_contents($path);

    expect($model->getFillable())->toEqualCanonicalizing([
        'name',
        'description',
        'is_active',
        'created_by',
    ])
        ->and($model->getCasts())->toHaveKey('is_active', 'boolean')
        ->and($contents)->toContain('function products(): HasMany');
});

it('relaciona producto con categoría', function () {
    $productPath = dirname(__DIR__, 2).'/app/Models/OperationInventoryProduct.php';
    $productContents = file_get_contents($productPath);

    expect((new OperationInventoryProduct)->getFillable())->toContain('operation_inventory_product_category_id')
        ->and($productContents)->toContain('function category(): BelongsTo');
});

it('define las tres categorías base en el seeder', function () {
    $path = dirname(__DIR__, 2).'/database/seeders/OperationInventoryProductCategorySeeder.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("'Medicamento'")
        ->toContain("'Equipo Medico'")
        ->toContain("'Mobiliario'")
        ->and(class_exists(OperationInventoryProductCategorySeeder::class))->toBeTrue();
});
