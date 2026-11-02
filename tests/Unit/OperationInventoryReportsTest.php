<?php

declare(strict_types=1);

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Filament\Operations\Pages\GeneradorDeReportes;
use App\Models\User;
use App\Support\Operations\Reports\OperationInventoryReportAccess;
use App\Support\Operations\Reports\OperationInventoryReportBuilder;
use App\Support\Operations\Reports\OperationReportBuilder;
use App\Support\Operations\Reports\OperationReportData;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Escenario propio dentro de una transacción que siempre se revierte: dos
 * ubicaciones y dos productos nuevos, con movimientos en fechas conocidas.
 * Todos los reportes se acotan a ese escenario con los filtros de ubicación
 * y producto, así los datos reales no alteran los números esperados.
 *
 * Producto A, ubicación 1: entra 20 el 05/09; sale 4 el 10/09, 3 el 02/10 y
 * un ajuste de 3 el 03/10. Existencia en almacén 10 (cuadra con el libro) y
 * 12 en la ficha (no cuadra). Costo 2,50.
 * Producto B, ubicación 1: existencia 1, salida de 3 hace 5 días, sin entradas.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake(OperationReportGenerator::DISK);

    $this->usuario = User::factory()->make([
        'id' => 999_999_131,
        'name' => 'QA INVENTARIO',
        'email' => 'qa.inventario@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]);

    $this->actingAs($this->usuario);
    Filament::setCurrentPanel('operations');

    $now = now()->toDateTimeString();
    $this->categoria = DB::table('operation_inventory_product_categories')->insertGetId(['name' => 'QA CATEGORIA', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $this->ubicacion1 = DB::table('operation_inventory_ubications')->insertGetId(['name' => 'QA ALMACEN 1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $this->ubicacion2 = DB::table('operation_inventory_ubications')->insertGetId(['name' => 'QA ALMACEN 2', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

    $producto = fn (string $code, string $name, float $cost): int => DB::table('operation_inventory_products')->insertGetId([
        'operation_inventory_product_category_id' => $this->categoria,
        'code' => $code, 'name' => $name, 'cost' => $cost, 'unit' => 'UNIDAD', 'presentation' => 'CAJA',
        'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->productoA = $producto('QA-A', 'QA PRODUCTO A', 2.5);
    $this->productoB = $producto('QA-B', 'QA PRODUCTO B', 0);

    $ficha = fn (int $product, int $ubication, int $existence): int => DB::table('operation_inventories')->insertGetId([
        'operation_inventory_product_id' => $product, 'operation_inventory_ubication_id' => $ubication,
        'name' => 'QA', 'unit' => 'UNIDAD', 'operation_inventory_type_id' => 1, 'existence' => $existence, 'cost' => 0,
        'created_by' => 'QA', 'min_stock' => 0, 'is_active' => 1, 'is_covered' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $stock = fn (int $product, int $ubication, int $existence) => DB::table('operation_inventory_product_stocks')->insert([
        'operation_inventory_product_id' => $product, 'operation_inventory_ubication_id' => $ubication,
        'existence' => $existence, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $fichaA = $ficha($this->productoA, $this->ubicacion1, 12);
    $fichaB = $ficha($this->productoB, $this->ubicacion1, 1);
    $stock($this->productoA, $this->ubicacion1, 10);
    $stock($this->productoB, $this->ubicacion1, 1);
    $stock($this->productoA, $this->ubicacion2, 0);

    DB::table('operation_inventory_entries')->insert([
        'operation_inventory_id' => $fichaA, 'operation_inventory_product_id' => $this->productoA, 'operation_inventory_ubication_id' => $this->ubicacion1,
        'operation_inventory_type_id' => 1, 'quantity' => 20, 'type_entry' => 'REPOSICIÓN DE INVENTARIO', 'created_by' => 'QA',
        'created_at' => '2026-09-05 10:00:00', 'updated_at' => '2026-09-05 10:00:00',
    ]);

    $salida = fn (int $fichaId, int $product, int $quantity, string $reason, string $at, ?int $case = null) => DB::table('operation_inventory_outflows')->insert([
        'operation_inventory_id' => $fichaId, 'operation_inventory_product_id' => $product, 'operation_inventory_ubication_id' => $this->ubicacion1,
        'telemedicine_case_id' => $case, 'operation_inventory_type_id' => 1, 'quantity' => $quantity, 'type_entry' => $reason,
        'created_by' => 'QA', 'created_at' => $at, 'updated_at' => $at,
    ]);

    $salida($fichaA, $this->productoA, 4, 'SALIDA TELEMEDICINA', '2026-09-10 09:00:00');
    $salida($fichaA, $this->productoA, 3, 'SALIDA TELEMEDICINA', '2026-10-02 11:00:00');
    $salida($fichaA, $this->productoA, 3, 'AJUSTE DE INVENTARIO', '2026-10-03 08:00:00');
    $salida($fichaB, $this->productoB, 3, 'SALIDA INSUMOS TELEMEDICINA', now()->subDays(5)->toDateTimeString());
});

afterEach(fn () => DB::rollBack());

function escenario(array $extra = []): OperationReportFilters
{
    return OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => '2026-10-01',
        'to' => '2026-10-31',
        'ubications' => [test()->ubicacion1, test()->ubicacion2],
        'products' => [test()->productoA, test()->productoB],
        ...$extra,
    ]);
}

/**
 * @return list<list<scalar|null>>
 */
function filas(OperationReportData $data): array
{
    return is_array($data->rows) ? $data->rows : iterator_to_array($data->rows, false);
}

/**
 * @return list<scalar|null>
 */
function filaDe(OperationReportData $data, string $product, ?string $ubication = null): array
{
    foreach (filas($data) as $row) {
        if (in_array($product, $row, true) && ($ubication === null || in_array($ubication, $row, true))) {
            return array_combine($data->headings, $row);
        }
    }

    throw new RuntimeException("No hay fila para {$product}");
}

/*
 * ---------------------------------------------------------------------------
 * Tipos
 * ---------------------------------------------------------------------------
 */

it('separa los reportes de servicios y de inventario', function (): void {
    expect(OperationReportType::forGroup(OperationReportType::GROUP_INVENTORY))->toHaveCount(7)
        ->and(OperationReportType::ServiceDetail->isInventory())->toBeFalse()
        ->and(OperationReportType::InventoryGeneral->usesPeriod())->toBeFalse()
        ->and(OperationReportType::InventoryLowStock->usesPeriod())->toBeFalse()
        ->and(OperationReportType::InventoryReconciliation->usesPeriod())->toBeFalse()
        ->and(OperationReportType::InventoryMovements->usesPeriod())->toBeTrue()
        ->and(OperationReportType::InventoryMovements->isDetail())->toBeTrue()
        ->and(OperationReportType::InventoryMovements->rowNoun())->toBe(['movimiento', 'movimientos'])
        ->and(OperationReportType::ServiceDetail->usesPeriod())->toBeTrue()
        ->and(OperationReportType::FullTable->usesPeriod())->toBeFalse();
});

it('normaliza los filtros de inventario y descarta estados desconocidos', function (): void {
    $filtros = OperationReportFilters::fromArray([
        'ubications' => ['3', 3, 'x', -1, 0, null],
        'products' => [7, '8'],
        'stock_statuses' => ['low', 'inventado', 'out'],
    ]);

    expect($filtros->ubications)->toBe([3])
        ->and($filtros->products)->toBe([7, 8])
        ->and($filtros->stockStatuses)->toBe(['low', 'out'])
        ->and(OperationReportFilters::fromArray($filtros->toArray()))->toEqual($filtros);
});

/*
 * ---------------------------------------------------------------------------
 * Números de cada reporte
 * ---------------------------------------------------------------------------
 */

it('el inventario general suma por ubicación, valoriza y marca el estado', function (): void {
    $umbral = OperationInventoryReportBuilder::lowStockThreshold();
    $reporte = OperationReportBuilder::build(OperationReportType::InventoryGeneral, escenario());
    $a = filaDe($reporte, 'QA PRODUCTO A');
    $b = filaDe($reporte, 'QA PRODUCTO B');

    expect($a['QA ALMACEN 1'])->toBe(10)
        ->and($a['QA ALMACEN 2'])->toBe(0)
        ->and($a['Existencia total'])->toBe(10)
        ->and($a['Valor (USD)'])->toBe(25.0)
        ->and($a['Estado'])->toBe($umbral >= 10 ? 'Stock bajo' : 'Disponible')
        ->and($b['Estado'])->toBe($umbral >= 1 ? 'Stock bajo' : 'Disponible')
        ->and($reporte->totals)->toBe(['TOTAL', '', '', '', '', 11, 0, 11, '', 25.0, ''])
        ->and(implode(' ', $reporte->criteria))->toContain('1 de 2 productos no tienen costo');
});

it('el inventario general filtra por estado del stock', function (): void {
    $soloAgotados = OperationReportBuilder::build(OperationReportType::InventoryGeneral, escenario(['stock_statuses' => ['out']]));

    expect(filas($soloAgotados))->toBe([])
        ->and($soloAgotados->totals)->toBeNull();
});

it('el stock bajo calcula consumo diario y días de cobertura sin contar ajustes', function (): void {
    $umbral = OperationInventoryReportBuilder::lowStockThreshold();

    if ($umbral < 1) {
        $this->markTestSkipped('El umbral de stock bajo es 0.');
    }

    $b = filaDe(OperationReportBuilder::build(OperationReportType::InventoryLowStock, escenario()), 'QA PRODUCTO B');

    expect($b['Existencia total'])->toBe(1)
        ->and($b['Salidas últimos 30 días'])->toBe(3)
        ->and($b['Consumo diario'])->toBe(0.1)
        ->and($b['Días de cobertura'])->toBe(10.0)
        ->and($b['Estado'])->toBe('Stock bajo');
});

it('el kárdex lista cada movimiento del periodo con signo y neto', function (): void {
    $periodo = escenario(['to' => '2026-10-20']);
    $reporte = OperationReportBuilder::build(OperationReportType::InventoryMovements, $periodo);
    $filas = filas($reporte);

    expect($filas)->toHaveCount(2)
        ->and($filas[0][0])->toBe('02/10/2026 11:00 am')
        ->and($filas[0][1])->toBe('Salida')
        ->and($filas[0][7])->toBe(-3)
        ->and($filas[1][2])->toBe('AJUSTE DE INVENTARIO')
        ->and($reporte->totals[7])->toBe(-6)
        ->and(OperationReportBuilder::count(OperationReportType::InventoryMovements, $periodo))->toBe(2);
});

it('los saldos se reconstruyen hacia atrás y cuadran', function (): void {
    $a = filaDe(OperationReportBuilder::build(OperationReportType::InventoryBalance, escenario()), 'QA PRODUCTO A', 'QA ALMACEN 1');

    expect($a['Saldo inicial'])->toBe(16)
        ->and($a['Entradas'])->toBe(0)
        ->and($a['Salidas'])->toBe(6)
        ->and($a['Saldo final'])->toBe(10)
        ->and($a['Observación'])->toBe('');

    $septiembre = filaDe(OperationReportBuilder::build(OperationReportType::InventoryBalance, escenario([
        'from' => '2026-09-01', 'to' => '2026-09-30',
    ])), 'QA PRODUCTO A', 'QA ALMACEN 1');

    expect($septiembre['Saldo inicial'])->toBe(0)
        ->and($septiembre['Entradas'])->toBe(20)
        ->and($septiembre['Salidas'])->toBe(4)
        ->and($septiembre['Saldo final'])->toBe(16);
});

it('un saldo negativo se marca para revisar', function (): void {
    $b = filaDe(OperationReportBuilder::build(OperationReportType::InventoryBalance, escenario([
        'from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString(),
    ])), 'QA PRODUCTO B');

    expect($b['Saldo inicial'])->toBe(4)
        ->and($b['Saldo final'])->toBe(1);

    $sinMovimientos = filaDe(OperationReportBuilder::build(OperationReportType::InventoryReconciliation, escenario()), 'QA PRODUCTO B');

    expect($sinMovimientos['Diferencia sin respaldo'])->toBe(4)
        ->and($sinMovimientos['Hallazgo'])->toContain('sin entradas que la respalden');
});

it('el consumo separa motivos y excluye los ajustes del consumo real', function (): void {
    $reporte = OperationReportBuilder::build(OperationReportType::InventoryConsumption, escenario());
    $a = filaDe($reporte, 'QA PRODUCTO A');

    expect($a['SALIDA TELEMEDICINA'])->toBe(3)
        ->and($a['AJUSTE DE INVENTARIO'])->toBe(3)
        ->and($a['Total salidas'])->toBe(6)
        ->and($a['Consumo (sin ajustes)'])->toBe(3)
        ->and($a['Consumo diario'])->toBe(0.1)
        ->and(count($reporte->totals))->toBe(count($reporte->headings));
});

it('sin movimiento lista lo que tiene existencia y no salió en el periodo', function (): void {
    $sinSalidas = OperationReportBuilder::build(OperationReportType::InventoryIdle, escenario(['from' => '2026-09-11', 'to' => '2026-09-30']));
    $a = filaDe($sinSalidas, 'QA PRODUCTO A');

    expect($a['Existencia total'])->toBe(10)
        ->and($a['Última salida'])->toBe('02/10/2026')
        ->and($a['Días sin salida'])->toBe((int) CarbonImmutable::parse('2026-10-02')->diffInDays(CarbonImmutable::now()->startOfDay()));

    $octubre = OperationReportBuilder::build(OperationReportType::InventoryIdle, escenario());

    expect(array_column(filas($octubre), 1))->not->toContain('QA PRODUCTO A');
});

it('la conciliación detecta almacén y ficha distintos aunque el libro cuadre', function (): void {
    $a = filaDe(OperationReportBuilder::build(OperationReportType::InventoryReconciliation, escenario()), 'QA PRODUCTO A', 'QA ALMACEN 1');

    expect($a['Existencia en almacén'])->toBe(10)
        ->and($a['Existencia en ficha'])->toBe(12)
        ->and($a['Diferencia almacén − ficha'])->toBe(-2)
        ->and($a['Saldo según movimientos'])->toBe(10)
        ->and($a['Diferencia sin respaldo'])->toBe(0)
        ->and($a['Hallazgo'])->toBe('Almacén y ficha no coinciden');
});

it('todas las filas y los totales tienen tantas columnas como encabezados', function (OperationReportType $type): void {
    $reporte = OperationReportBuilder::build($type, escenario(['from' => '2026-09-01']));

    foreach (filas($reporte) as $fila) {
        expect($fila)->toHaveCount(count($reporte->headings));
    }

    if ($reporte->totals !== null) {
        expect($reporte->totals)->toHaveCount(count($reporte->headings));
    }

    expect(max([...$reporte->numericColumns, -1]))->toBeLessThan(count($reporte->headings));
})->with(OperationReportType::forGroup(OperationReportType::GROUP_INVENTORY));

/*
 * ---------------------------------------------------------------------------
 * Archivos, acceso y pantalla
 * ---------------------------------------------------------------------------
 */

it('genera cada reporte de inventario en Excel, CSV y PDF', function (OperationReportType $type): void {
    foreach (OperationReportFormat::cases() as $format) {
        $archivo = OperationReportGenerator::filename($type, $format);
        $ruta = OperationReportGenerator::store($type, $format, escenario(['from' => '2026-09-01']), $this->usuario->id, $archivo);

        expect(Storage::disk(OperationReportGenerator::DISK)->size($ruta))->toBeGreaterThan(0);
    }
})->with(OperationReportType::forGroup(OperationReportType::GROUP_INVENTORY));

it('un usuario de proveedor no puede generar reportes de inventario', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 999_999_132,
        'email' => 'proveedor@clinica.com',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 1,
    ]));

    expect(OperationInventoryReportAccess::allows())->toBeFalse();

    OperationReportGenerator::store(
        OperationReportType::InventoryGeneral,
        OperationReportFormat::Csv,
        escenario(),
        999_999_132,
        OperationReportGenerator::filename(OperationReportType::InventoryGeneral, OperationReportFormat::Csv),
    );
})->throws(AuthorizationException::class);

it('la pantalla ofrece la sección de inventario y adapta el resumen', function (): void {
    expect(OperationInventoryReportAccess::allows())->toBeTrue();

    Livewire::test(GeneradorDeReportes::class)
        ->assertSee('Inventario Diagnomóvil')
        ->assertSee('Conciliación de existencias')
        ->set('data.type', OperationReportType::InventoryGeneral->value)
        ->set('data.products', [$this->productoA, $this->productoB])
        ->assertSee('Este reporte muestra la existencia al momento de generarlo')
        ->assertSee('Se incluirán')
        ->assertSee('productos')
        ->assertDontSee('Por fecha de inicio del servicio');
});

it('la pantalla descarga al instante un reporte de inventario pequeño', function (): void {
    Livewire::test(GeneradorDeReportes::class)
        ->set('data.type', OperationReportType::InventoryLowStock->value)
        ->set('data.products', [$this->productoB])
        ->set('data.format', OperationReportFormat::Csv->value)
        ->call('generate')
        ->assertFileDownloaded();
});
