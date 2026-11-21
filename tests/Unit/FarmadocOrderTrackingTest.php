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

    if (Schema::hasTable('farmadoc_orders') && ! Schema::hasColumn('farmadoc_orders', 'service_order_number')) {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_19_180100_add_service_order_to_farmadoc_orders_table.php')->up();
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
    $placed = [
        'order_number' => 'IC-TEST-1',
        'order_id' => 77,
        'status' => 'pendiente',
        'service_order_number' => 'ORD-009',
        'service_order_id' => 9,
    ];

    $order = FarmadocOrderTracker::record($placed, $items, 9, 'COORD-9', 'Paciente Prueba', null);
    $again = FarmadocOrderTracker::record($placed, $items, 9, 'COORD-9', 'Paciente Prueba', null);

    expect($again->getKey())->toBe($order->getKey())
        ->and(FarmadocOrder::query()->where('order_number', 'IC-TEST-1')->count())->toBe(1)
        ->and($order->items_count)->toBe(2)
        ->and($order->units_count)->toBe(5)
        ->and($order->farmadoc_order_id)->toBe(77)
        ->and($order->service_order_number)->toBe('ORD-009')
        ->and($order->farmadoc_service_order_id)->toBe(9)
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
        ->and($console)->toContain('->everyFiveMinutes()')
        ->and($resource)->toContain("'INVENTARIO DIAGNOMOVIL'")
        ->and($resource)->toContain('userCanSeeInventoryAvailability')
        ->and($resource)->toContain('Pedidos a Farmadoc')
        ->and($page)->toContain('FarmadocOrderTracker::record(');
});

it('reconoce los estatus aceptado y enviado y nunca esconde uno desconocido como pendiente', function (string|null|int $remote, string $stored, string $label, bool $open): void {
    $status = FarmadocOrder::normalizeStatus($remote);

    expect($status)->toBe($stored)
        ->and(FarmadocOrder::statusLabel($status))->toBe($label)
        ->and(strlen($status))->toBeLessThanOrEqual(FarmadocOrder::STATUS_MAX_LENGTH)
        ->and((new FarmadocOrder(['status' => $status]))->isOpen())->toBe($open);
})->with([
    'pendiente' => ['pendiente', 'pendiente', 'Pendiente', true],
    'en proceso' => ['en-proceso', 'en-proceso', 'En proceso', true],
    'aceptado' => ['aceptado', 'aceptado', 'Aceptado', true],
    'aceptado con mayúsculas' => [' Aceptado ', 'aceptado', 'Aceptado', true],
    'enviado' => ['enviado', 'enviado', 'Enviado', false],
    'finalizado' => ['finalizado', 'finalizado', 'Finalizado', false],
    'desconocido' => ['por_despachar', 'por-despachar', 'Por despachar', true],
    'vacío' => ['', 'pendiente', 'Pendiente', true],
    'no es texto' => [5, 'pendiente', 'Pendiente', true],
    'nulo' => [null, 'pendiente', 'Pendiente', true],
]);

it('un pedido aceptado en Farmadoc queda Aceptado con su nota de entrega y sin montos', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-ACEPTADO*' => Http::response([
            'data' => [
                'order_id' => 9,
                'order_number' => 'IC-ACEPTADO',
                'status' => 'aceptado',
                'status_label' => 'Aceptado',
                'total' => 44.18,
                'updated_at' => '2026-11-20T18:21:15-04:00',
                'acceptance' => [
                    'delivery_note_number' => 'NEP-0000002',
                    'accepted_at' => '2026-11-20T18:21:15-04:00',
                    'accepted_total_usd' => 44.18,
                    'items' => [['product_name' => 'X', 'unit_price_usd' => 14.78]],
                ],
            ],
        ]),
    ]);

    $order = FarmadocOrder::factory()->create(['order_number' => 'IC-ACEPTADO']);

    FarmadocOrderTracker::refresh($order);
    $order->refresh();

    expect($order->status)->toBe(FarmadocOrder::STATUS_ACCEPTED)
        ->and($order->isOpen())->toBeTrue()
        ->and(json_encode($order->getAttributes()))->not->toContain('44.18')
        ->and(json_encode($order->getAttributes()))->not->toContain('14.78');

    if (FarmadocOrder::acceptanceColumnsExist()) {
        expect($order->delivery_note_number)->toBe('NEP-0000002')
            ->and($order->accepted_at?->copy()->utc()->toIso8601String())->toBe('2026-11-20T22:21:15+00:00');
    }
});

it('el job sigue consultando los aceptados y deja de consultar los enviados', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-ACC*' => Http::response([
            'data' => ['order_number' => 'IC-ACC', 'status' => 'enviado', 'updated_at' => null],
        ]),
        '*' => Http::response(['message' => 'No debería consultarse'], 500),
    ]);

    $accepted = FarmadocOrder::factory()->create(['order_number' => 'IC-ACC', 'status' => FarmadocOrder::STATUS_ACCEPTED]);
    $sent = FarmadocOrder::factory()->create(['order_number' => 'IC-SENT', 'status' => FarmadocOrder::STATUS_SENT, 'status_checked_at' => null]);

    (new RefreshFarmadocOrderStatuses)->handle(new FarmadocAllyClient);

    expect($accepted->refresh()->status)->toBe(FarmadocOrder::STATUS_SENT)
        ->and($accepted->isOpen())->toBeFalse()
        ->and($sent->refresh()->status_checked_at)->toBeNull();

    Http::assertSentCount(1);
});

it('descarga la nota de entrega con el token del aliado y conserva el nombre del archivo', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-NOTA/nota-de-entrega*' => Http::response('%PDF-1.7 contenido', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="nota-entrega-NEP-0000002.pdf"',
        ]),
    ]);

    $note = (new FarmadocAllyClient)->deliveryNote('IC-NOTA');

    expect($note)->toBe(['content' => '%PDF-1.7 contenido', 'filename' => 'nota-entrega-NEP-0000002.pdf']);

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'partner_company=ALDO-2026-0021')
        && $request->hasHeader('Authorization', 'Bearer fd_test')
        && $request->hasHeader('Accept', 'application/pdf'));
});

it('la nota de entrega rechaza respuestas que no son PDF, nombres peligrosos y pedidos sin nota', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-HTML/*' => Http::response('<html>error</html>', 200, ['Content-Type' => 'text/html']),
        'https://farmasysdoc.test/api/external/orders/IC-RARO/*' => Http::response('%PDF-1.7', 200, ['Content-Disposition' => 'inline; filename="../../etc/passwd"']),
        'https://farmasysdoc.test/api/external/orders/IC-PEND/*' => Http::response(['message' => 'Este pedido todavía no tiene nota de entrega.'], 404),
    ]);

    $client = new FarmadocAllyClient;

    expect(fn () => $client->deliveryNote('IC-HTML'))->toThrow(FarmadocException::class, 'Farmadoc no devolvió la nota de entrega.')
        ->and($client->deliveryNote('IC-RARO')['filename'])->toBe('nota-entrega-IC-RARO.pdf')
        ->and(fn () => $client->deliveryNote('IC-PEND'))->toThrow(FarmadocException::class, 'Este pedido todavía no tiene nota de entrega.')
        ->and(fn () => $client->deliveryNote('../x'))->toThrow(FarmadocException::class, 'El número de pedido no es válido.');
});

it('solo los pedidos aceptados, enviados o con número de nota tienen nota de entrega', function (?string $status, ?string $note, bool $expected): void {
    expect((new FarmadocOrder(['status' => $status, 'delivery_note_number' => $note]))->hasDeliveryNote())->toBe($expected);
})->with([
    'pendiente' => ['pendiente', null, false],
    'en proceso' => ['en-proceso', null, false],
    'aceptado' => ['aceptado', null, true],
    'enviado' => ['enviado', null, true],
    'finalizado' => ['finalizado', null, true],
    'desconocido con nota' => ['por-despachar', 'NEP-1', true],
]);

it('la nota de entrega se sirve por una ruta autenticada que exige permiso y nunca expone el enlace de Farmadoc', function (): void {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Operations/FarmadocDeliveryNoteController.php');
    $actions = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Actions/FarmadocOrderActions.php');
    $table = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Tables/FarmadocOrdersTable.php');
    $view = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Pages/ViewFarmadocOrder.php');

    expect($routes)->toContain("Route::get('operations/farmadoc-orders/{farmadocOrder}/nota-de-entrega'")
        ->and($routes)->toContain("->name('operations.farmadoc-orders.delivery-note')")
        ->and($controller)->toContain('abort_unless(FarmadocOrderResource::canAccess(), 403)')
        ->and($controller)->toContain('abort_unless($farmadocOrder->hasDeliveryNote(), 404')
        ->and($controller)->toContain("'Cache-Control' => 'private, no-store, max-age=0'")
        ->and($controller)->toContain("\$request->boolean('descargar') ? 'attachment' : 'inline'")
        ->and($controller)->not->toContain('delivery_note_url')
        ->and($actions)->toContain('Route::has(self::DELIVERY_NOTE_ROUTE)')
        ->and($actions)->toContain('shouldOpenInNewTab: true')
        ->and($table)->toContain('FarmadocOrderActions::viewDeliveryNote()')
        ->and($table)->toContain('FarmadocOrderActions::downloadDeliveryNote()')
        ->and($view)->toContain('FarmadocOrderActions::viewDeliveryNote()')
        ->and($view)->toContain('FarmadocOrderActions::downloadDeliveryNote()');
});

it('guarda el código de barras de cada medicamento al registrar el pedido', function (): void {
    $catalog = App\Support\Farmadoc\FarmadocMedicationList::fromApiRows([
        ['product_id' => 15, 'name' => 'Acetaminofen', 'sku' => 'MED', 'barcode' => ' 7591234567890 '],
        ['product_id' => 16, 'name' => 'Sin código', 'sku' => null],
    ]);

    $items = App\Support\Farmadoc\FarmadocOrderDraft::items([
        ['product_id' => 15, 'quantity' => 2],
        ['product_id' => 16, 'quantity' => 1],
    ], $catalog);

    $order = FarmadocOrderTracker::record(['order_number' => 'IC-BARCODE', 'status' => 'pendiente'], $items, null, null, null, null);

    expect($items[0]['barcode_snapshot'])->toBe('7591234567890')
        ->and($items[1]['barcode_snapshot'])->toBeNull()
        ->and($order->refresh()->items[0]['barcode'])->toBe('7591234567890')
        ->and($order->items[1]['barcode'])->toBeNull();
});

it('muestra el código de barras de pedidos anteriores desde la aceptación o el catálogo en cache, sin llamar al API', function (): void {
    Http::fake();
    $previousCacheDriver = Illuminate\Support\Facades\Cache::getDefaultDriver();
    Illuminate\Support\Facades\Cache::setDefaultDriver('array');
    Illuminate\Support\Facades\Cache::put(App\Filament\Operations\Pages\FarmadocMedicationOrder::CACHE_KEY, [
        'medications' => [['product_id' => 3, 'name' => 'Del catálogo', 'barcode' => '7593333333333']],
    ], now()->addMinute());

    $order = new FarmadocOrder([
        'items' => [
            ['product_id' => 1, 'name' => 'Guardado', 'sku' => 'A', 'barcode' => '7591111111111', 'quantity' => 1],
            ['product_id' => 2, 'name' => 'Aceptado', 'sku' => 'B', 'quantity' => 2],
            ['product_id' => 3, 'name' => 'Del catálogo', 'sku' => null, 'quantity' => 3],
            ['product_id' => 4, 'name' => 'Sin dato', 'sku' => null, 'quantity' => 4],
        ],
        'acceptance_items' => [['name' => 'Aceptado', 'sku' => 'B', 'barcode' => '7592222222222']],
    ]);

    $barcodes = array_column(App\Support\Farmadoc\FarmadocOrderItems::forDisplay($order), 'barcode', 'name');

    expect($barcodes)->toBe([
        'Guardado' => '7591111111111',
        'Aceptado' => '7592222222222',
        'Del catálogo' => '7593333333333',
        'Sin dato' => null,
    ]);

    Http::assertNothingSent();
    Illuminate\Support\Facades\Cache::setDefaultDriver($previousCacheDriver);
});

it('la ficha del pedido muestra el código de barras en la sección de medicamentos', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/FarmadocOrders/Schemas/FarmadocOrderInfolist.php');

    expect($infolist)->toContain('FarmadocOrderItems::forDisplay($record)')
        ->and($infolist)->toContain("TextEntry::make('barcode')")
        ->and($infolist)->toContain("->label('Código de barras')");
});
