<?php

declare(strict_types=1);

use App\Jobs\RefreshFarmadocOrderStatuses;
use App\Models\FarmadocOrder;
use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocOrderTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    if (! Schema::hasTable('farmadoc_orders')) {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_19_103231_create_farmadoc_orders_table.php')->up();
    }

    DB::beginTransaction();

    config()->set('services.farmadoc', [
        'base_url' => 'https://farmasysdoc.test',
        'token' => 'fd_test',
        'partner_company' => 'ALDO-2026-0021',
        'client_id' => 12,
        'timeout' => 5,
    ]);
});

afterEach(fn () => DB::rollBack());

it('guarda el pedido enviado sin precios y no lo duplica', function (): void {
    $items = [
        ['product_id' => 15, 'quantity' => 2, 'unit_price' => 0.0, 'product_name_snapshot' => 'Acetaminofen', 'sku_snapshot' => 'MED'],
        ['product_id' => 16, 'quantity' => 3, 'unit_price' => 0.0, 'product_name_snapshot' => 'Ibuprofeno', 'sku_snapshot' => null],
    ];
    $placed = ['order_number' => 'IC-TEST-1', 'order_id' => 77, 'status' => 'pendiente'];

    $order = FarmadocOrderTracker::record($placed, $items, 9, 'COORD-9', 'Paciente Prueba', null);
    $again = FarmadocOrderTracker::record($placed, $items, 9, 'COORD-9', 'Paciente Prueba', null);

    expect($again->getKey())->toBe($order->getKey())
        ->and(FarmadocOrder::query()->where('order_number', 'IC-TEST-1')->count())->toBe(1)
        ->and($order->items_count)->toBe(2)
        ->and($order->units_count)->toBe(5)
        ->and($order->farmadoc_order_id)->toBe(77)
        ->and($order->status)->toBe(FarmadocOrder::STATUS_PENDING)
        ->and($order->items[0])->toBe(['product_id' => 15, 'name' => 'Acetaminofen', 'sku' => 'MED', 'quantity' => 2])
        ->and(json_encode($order->items))->not->toContain('price');
});

it('actualiza el estatus desde Farmadoc y descarta los montos', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-TEST-2*' => Http::response([
            'data' => [
                'order_id' => 80,
                'order_number' => 'IC-TEST-2',
                'status' => 'en-proceso',
                'items_count' => 1,
                'total' => 150.5,
                'updated_at' => '2026-11-19T10:00:00-04:00',
            ],
        ]),
    ]);

    $order = FarmadocOrder::factory()->create(['order_number' => 'IC-TEST-2']);

    FarmadocOrderTracker::refresh($order);
    $order->refresh();

    expect($order->status)->toBe(FarmadocOrder::STATUS_IN_PROGRESS)
        ->and($order->status_check_error)->toBeNull()
        ->and($order->status_checked_at)->not->toBeNull()
        ->and($order->farmadoc_updated_at)->not->toBeNull()
        ->and($order->getAttributes())->not->toHaveKey('total');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'partner_company=ALDO-2026-0021')
        && $request->hasHeader('Authorization', 'Bearer fd_test'));
});

it('si Farmadoc no encuentra el pedido conserva el estatus y guarda el motivo', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/*' => Http::response([
            'message' => 'No se encontró un pedido de ese aliado con ese identificador.',
        ], 404),
    ]);

    $order = FarmadocOrder::factory()->create(['order_number' => 'IC-TEST-3']);

    expect(fn () => FarmadocOrderTracker::refresh($order))->toThrow(FarmadocException::class);

    $order->refresh();

    expect($order->status)->toBe(FarmadocOrder::STATUS_PENDING)
        ->and($order->status_check_error)->toBe('No se encontró un pedido de ese aliado con ese identificador.');
});

it('rechaza un número de pedido inválido sin llamar al API', function (): void {
    Http::fake();

    expect(fn () => (new FarmadocAllyClient)->orderStatus('../branches'))->toThrow(FarmadocException::class);

    Http::assertNothingSent();
});

it('el job solo consulta los pedidos abiertos', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-OPEN*' => Http::response([
            'data' => ['order_number' => 'IC-OPEN', 'status' => 'finalizado', 'updated_at' => null],
        ]),
        '*' => Http::response(['message' => 'No debería consultarse'], 500),
    ]);

    $open = FarmadocOrder::factory()->create(['order_number' => 'IC-OPEN']);
    $closed = FarmadocOrder::factory()->completed()->create(['order_number' => 'IC-CLOSED']);

    (new RefreshFarmadocOrderStatuses)->handle(new FarmadocAllyClient);

    expect($open->refresh()->status)->toBe(FarmadocOrder::STATUS_COMPLETED)
        ->and($closed->refresh()->status_checked_at)->toBeNull();

    Http::assertSentCount(1);
});

it('el menú, el permiso y la tarea programada quedan registrados', function (): void {
    $root = dirname(__DIR__, 2);
    $registry = file_get_contents($root.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php');
    $console = file_get_contents($root.'/routes/console.php');
    $resource = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/FarmadocOrderResource.php');
    $page = file_get_contents($root.'/app/Filament/Operations/Pages/FarmadocMedicationOrder.php');

    expect($registry)->toContain("FarmadocOrderResource::class => ['pedidos-farmadoc', 'servicios-medicos']")
        ->and($console)->toContain('Schedule::job(new RefreshFarmadocOrderStatuses')
        ->and($console)->toContain('->everyThirtyMinutes()')
        ->and($resource)->toContain("'INVENTARIO DIAGNOMOVIL'")
        ->and($resource)->toContain('userCanSeeInventoryAvailability')
        ->and($resource)->toContain('Pedidos a Farmadoc')
        ->and($page)->toContain('FarmadocOrderTracker::record(');
});
