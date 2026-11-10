<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\ListOperationInventoryProducts;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\ViewOperationInventoryProduct;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Models\User;
use App\Support\Operations\OperationInventoryListHeaders;
use App\Support\Operations\OperationInventoryProductPricing;
use App\Support\Operations\OperationInventoryProductStockStatus;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo monta pantallas y lee: el usuario es `make()` y todo corre en una transacción revertida
 * (Parámetros de Inventario usa firstOrCreate).
 */
beforeEach(function (): void {
    if (! Schema::hasColumn('operation_inventory_product_categories', 'profit_percentage')) {
        $this->markTestSkipped('Falta la columna profit_percentage en esta conexión.');
    }

    DB::beginTransaction();

    Filament::setCurrentPanel('operations');

    $this->actingAs(User::factory()->make([
        'id' => 999_778,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));
});

afterEach(fn () => DB::rollBack());

function productForHeader(mixed $cost, mixed $percentage, int $existence, ?string $categoryName = 'Medicamento'): OperationInventoryProduct
{
    $product = new OperationInventoryProduct([
        'code' => 'P-900',
        'name' => 'Ibuprofeno 400 mg',
        'cost' => $cost,
        'unit' => 'CAJA',
        'is_active' => true,
    ]);
    $product->setRelation('category', $categoryName === null
        ? null
        : new OperationInventoryProductCategory(['name' => $categoryName, 'profit_percentage' => $percentage]));
    $product->setRelation('stocks', collect([new App\Models\OperationInventoryProductStock(['existence' => $existence])]));

    return $product;
}

it('clasifica la existencia con la misma regla del listado', function (int $existence, string $expected): void {
    expect(OperationInventoryProductStockStatus::for($existence, 3))->toBe($expected);
})->with([
    'sin unidades' => [0, OperationInventoryProductStockStatus::OUT_OF_STOCK],
    'negativo' => [-2, OperationInventoryProductStockStatus::OUT_OF_STOCK],
    'en el umbral' => [3, OperationInventoryProductStockStatus::LOW_STOCK],
    'una unidad' => [1, OperationInventoryProductStockStatus::LOW_STOCK],
    'sobre el umbral' => [4, OperationInventoryProductStockStatus::AVAILABLE],
]);

it('el encabezado de la ficha muestra código, categoría, existencia y precio', function (): void {
    $html = (string) OperationInventoryListHeaders::product(productForHeader(100, 30, 12));

    expect($html)
        ->toContain('Ibuprofeno 400 mg')
        ->toContain('P-900')
        ->toContain('Medicamento')
        ->toContain('Disponible')
        ->toContain('12 und.')
        ->toContain('30,00 %')
        ->toContain('130.00')
        ->not->toContain('Defina el porcentaje');
});

it('el encabezado explica por qué no hay precio final', function (?string $category, mixed $percentage, string $reason): void {
    $html = (string) OperationInventoryListHeaders::product(productForHeader(50, $percentage, 0, $category));

    expect($html)
        ->toContain('Sin existencia')
        ->toContain('Sin definir')
        ->toContain(e($reason));
})->with([
    'categoría sin porcentaje' => ['Insumos', null, 'Defina el porcentaje de utilidad en la categoría «Insumos».'],
    'sin categoría' => [null, null, 'Asigne una categoría al producto para calcular su precio.'],
]);

it('no repite unidad y presentación cuando dicen lo mismo', function (): void {
    $product = productForHeader(10, 10, 5);
    $product->unit = 'UNIDAD';
    $product->presentation = App\Enums\OperationInventoryProductPresentation::Unidad;

    expect(substr_count(mb_strtolower(strip_tags((string) OperationInventoryListHeaders::product($product))), 'unidad'))->toBe(1);
});

it('calcula la utilidad por unidad en USD', function (): void {
    expect(OperationInventoryProductPricing::profitAmountFor(productForHeader(80, 25, 1)))->toBe(20.0)
        ->and(OperationInventoryProductPricing::profitAmountFor(productForHeader(80, null, 1)))->toBeNull();
});

it('el listado de productos resume los que no tienen precio ni costo', function (): void {
    $html = (string) OperationInventoryListHeaders::products();

    if (OperationInventoryProduct::query()->doesntExist()) {
        expect($html)->toContain('Productos');

        return;
    }

    expect($html)->toContain('Sin precio definido')->toContain('Sin costo');
});

it('la ficha conserva las tres pestañas y suma precio y movimientos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Schemas/OperationInventoryProductInfolist.php');

    expect($source)
        ->toContain("Tab::make('Producto')")
        ->toContain("Tab::make('Existencia')")
        ->toContain("Tab::make('Registro')")
        ->toContain("Section::make('Precio de venta')")
        ->toContain("Section::make('Últimos movimientos')")
        ->toContain('->persistTab()');
});

it('abre la lista y la ficha del producto sin errores', function (): void {
    Livewire::test(ListOperationInventoryProducts::class)->assertSuccessful()->assertSee('Sin precio definido');

    $product = OperationInventoryProduct::query()->whereHas('stocks')->orderBy('id')->first()
        ?? OperationInventoryProduct::query()->orderBy('id')->first();

    if ($product === null) {
        return;
    }

    Livewire::test(ViewOperationInventoryProduct::class, ['record' => $product->getKey()])
        ->assertSuccessful()
        ->assertSee('Inventario Diagnomóvil · Producto', false)
        ->assertSee('Precio final')
        ->assertSee('Existencia');
});
