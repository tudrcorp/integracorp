<?php

declare(strict_types=1);

use App\Enums\ApiPartnerAbility;
use App\Models\FarmadocOrder;
use App\Models\OperationInventory;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use App\Models\User;
use App\Support\ApiPartners\FarmadocOrderFeed;
use App\Support\Farmadoc\FarmadocInventoryReceipt;
use App\Support\Farmadoc\FarmadocReceiptLabels;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function receiptRoot(): string
{
    return dirname(__DIR__, 2);
}

it('solo un pedido enviado y sin entrada previa se puede registrar en inventario', function (string $status, bool $received, bool $expected): void {
    if (! FarmadocOrder::receiptColumnsExist()) {
        $this->markTestSkipped('Falta la migración 2026_11_21_100000.');
    }

    $order = new FarmadocOrder(['status' => $status, 'received_at' => $received ? now() : null]);

    expect($order->canBeReceived())->toBe($expected);
})->with([
    'pendiente' => ['pendiente', false, false],
    'aceptado' => ['aceptado', false, false],
    'enviado' => ['enviado', false, true],
    'finalizado' => ['finalizado', false, true],
    'enviado pero ya recibido' => ['enviado', true, false],
    'registrado en inventario' => [FarmadocOrder::STATUS_RECEIVED, true, false],
]);

it('«Registrado en inventario» es un estatus cerrado, con etiqueta y nota de entrega', function (): void {
    $order = new FarmadocOrder(['status' => FarmadocOrder::STATUS_RECEIVED]);

    expect(FarmadocOrder::statusLabel(FarmadocOrder::STATUS_RECEIVED))->toBe('Registrado en inventario')
        ->and(FarmadocOrder::statusColor(FarmadocOrder::STATUS_RECEIVED))->toBe('success')
        ->and($order->isOpen())->toBeFalse()
        ->and($order->isReceived())->toBeTrue()
        ->and($order->hasDeliveryNote())->toBeTrue()
        ->and(FarmadocOrder::receiptNumberFor(7))->toBe('NRA-000007')
        ->and(FarmadocOrder::receiptNumberFor(1234567))->toBe('NRA-1234567');
});

it('una consulta a Farmadoc nunca pisa el estatus «Registrado en inventario»', function (): void {
    $tracker = file_get_contents(receiptRoot().'/app/Support/Farmadoc/FarmadocOrderTracker.php');

    expect($tracker)->toContain("\$order->isReceived() ? FarmadocOrder::STATUS_RECEIVED : FarmadocOrder::normalizeStatus(\$remote['status'])");
});

it('lee la elección del analista sin aceptar valores inventados', function (mixed $target, ?int $expected): void {
    expect(FarmadocInventoryReceipt::productIdFromTarget($target))->toBe($expected);
})->with([
    'producto' => ['product:15', 15],
    'nuevo' => ['new', null],
    'cero' => ['product:0', null],
    'negativo' => ['product:-3', null],
    'inyección' => ['product:1 OR 1=1', null],
    'nulo' => [null, null],
    'número suelto' => [15, null],
]);

it('nombra lo que se hizo con cada medicamento', function (): void {
    expect(FarmadocReceiptLabels::action(FarmadocInventoryReceipt::ACTION_EXACT))->toBe('Sumado (coincidía por código)')
        ->and(FarmadocReceiptLabels::action(FarmadocInventoryReceipt::ACTION_UPDATE))->toBe('Producto actualizado y sumado')
        ->and(FarmadocReceiptLabels::action(FarmadocInventoryReceipt::ACTION_CREATE))->toBe('Producto nuevo')
        ->and(FarmadocReceiptLabels::action(FarmadocInventoryReceipt::ACTION_SKIPPED))->toBe('No llegó')
        ->and(FarmadocReceiptLabels::lines(new FarmadocOrder(['receipt_items' => [
            ['name' => 'LOREX', 'quantity_ordered' => 2, 'quantity_received' => 1, 'action' => 'create', 'product_name' => 'LOREX'],
        ]]))[0]['received_label'])->toBe('1 de 2');
});

it('el API muestra la recepción a Farmadoc sin datos del paciente ni montos', function (): void {
    $order = new FarmadocOrder([
        'order_number' => 'IC-1-20261121-1111',
        'status' => FarmadocOrder::STATUS_RECEIVED,
        'patient_name' => 'Paciente Secreto',
        'receipt_number' => 'NRA-000001',
        'received_at' => now(),
        'received_by_name' => 'Analista',
        'receipt_items' => [
            ['name' => 'A', 'barcode' => '759', 'sku' => 'S', 'quantity_ordered' => 2, 'quantity_received' => 2],
            ['name' => 'B', 'barcode' => null, 'sku' => null, 'quantity_ordered' => 2, 'quantity_received' => 1],
        ],
    ]);

    $payload = FarmadocOrderFeed::payload($order, 'https://example.test/nota');

    expect($payload['estatus'])->toBe('registrado-inventario')
        ->and($payload['recepcion']['nota_recibido'])->toBe('NRA-000001')
        ->and($payload['recepcion']['completo'])->toBeFalse()
        ->and($payload['recepcion']['medicamentos'][1]['cantidad_recibida'])->toBe(1)
        ->and(json_encode($payload))->not->toContain('Paciente Secreto');

    $pending = FarmadocOrderFeed::payload(new FarmadocOrder(['order_number' => 'IC-2', 'status' => 'enviado']), 'https://example.test/nota');

    expect($pending['recepcion'])->toBeNull();
});

it('da entrada al pedido: suma por código, actualiza el producto elegido, crea el nuevo y no deja cargar dos veces', function (): void {
    if (! FarmadocOrder::receiptColumnsExist()) {
        $this->markTestSkipped('Falta la migración 2026_11_21_100000.');
    }

    $ubication = OperationInventoryUbication::query()->where('is_active', true)->firstOrFail();
    $exact = OperationInventoryProduct::factory()->create(['code' => '7590000000011', 'name' => 'EXACTO']);
    $toUpdate = OperationInventoryProduct::factory()->create(['code' => 'VIEJO-1', 'name' => 'NOMBRE VIEJO']);

    $order = FarmadocOrder::factory()->create([
        'status' => FarmadocOrder::STATUS_SENT,
        'items' => [
            ['product_id' => null, 'name' => 'EXACTO FARMADOC', 'sku' => 'CSV-7590000000011', 'barcode' => '7590000000011', 'quantity' => 2],
            ['product_id' => null, 'name' => 'LORATADINA 10MG 10COMP', 'sku' => 'CSV-7590000000022', 'barcode' => '7590000000022', 'active_ingredient' => 'LORATADINA', 'laboratory' => 'DOLLDER', 'quantity' => 3],
            ['product_id' => null, 'name' => 'NUEVO 5MG', 'sku' => 'CSV-7590000000033', 'barcode' => '7590000000033', 'quantity' => 2],
            ['product_id' => null, 'name' => 'NO LLEGO', 'sku' => 'CSV-7590000000044', 'barcode' => '7590000000044', 'quantity' => 1],
        ],
    ]);

    $plan = FarmadocInventoryReceipt::plan($order);
    $keys = array_column($plan, 'key', 'name');

    expect($plan[0]['exact_product_id'])->toBe($exact->id)
        ->and($plan[1]['exact_product_id'])->toBeNull();

    $user = User::factory()->make(['name' => 'Analista Prueba']);

    $result = FarmadocInventoryReceipt::register($order, (int) $ubication->id, [
        $keys['EXACTO FARMADOC'] => ['target' => null, 'quantity' => 2],
        $keys['LORATADINA 10MG 10COMP'] => ['target' => 'product:'.$toUpdate->id, 'quantity' => 2],
        $keys['NUEVO 5MG'] => ['target' => 'new', 'quantity' => 2],
        $keys['NO LLEGO'] => ['target' => null, 'quantity' => 0],
    ], $user, 'Faltó una de loratadina');

    $toUpdate->refresh();
    $created = OperationInventoryProduct::query()->where('code', '7590000000033')->first();

    expect($result['exact'])->toBe(1)
        ->and($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(1)
        ->and($result['units'])->toBe(6)
        ->and($toUpdate->name)->toBe('LORATADINA 10MG 10COMP')
        ->and($toUpdate->code)->toBe('7590000000022')
        ->and($created)->not->toBeNull()
        ->and(OperationInventory::query()->where('operation_inventory_product_id', $toUpdate->id)->value('laboratory'))->toBe('DOLLDER')
        ->and((int) OperationInventoryProductStock::query()->where(['operation_inventory_product_id' => $exact->id, 'operation_inventory_ubication_id' => $ubication->id])->value('existence'))->toBe(2)
        ->and($order->status)->toBe(FarmadocOrder::STATUS_RECEIVED)
        ->and($order->receipt_number)->toBe(FarmadocOrder::receiptNumberFor($order->id))
        ->and(collect($order->receipt_items)->firstWhere('name', 'NO LLEGO')['action'])->toBe('skipped')
        ->and(collect($order->receipt_items)->firstWhere('name', 'LORATADINA 10MG 10COMP')['previous']['name'])->toBe('NOMBRE VIEJO');

    expect(fn () => FarmadocInventoryReceipt::register($order, (int) $ubication->id, [], $user))
        ->toThrow(InvalidArgumentException::class, 'Este pedido ya se registró en el inventario');
});

it('rechaza cantidades fuera de rango, el mismo producto para dos medicamentos y una entrada vacía', function (): void {
    if (! FarmadocOrder::receiptColumnsExist()) {
        $this->markTestSkipped('Falta la migración 2026_11_21_100000.');
    }

    $ubication = OperationInventoryUbication::query()->where('is_active', true)->firstOrFail();
    $product = OperationInventoryProduct::factory()->create(['code' => 'DUP-1']);
    $order = FarmadocOrder::factory()->create([
        'status' => FarmadocOrder::STATUS_SENT,
        'items' => [
            ['product_id' => null, 'name' => 'UNO SIN CODIGO', 'sku' => null, 'quantity' => 2],
            ['product_id' => null, 'name' => 'DOS SIN CODIGO', 'sku' => null, 'quantity' => 2],
        ],
    ]);
    $keys = array_column(FarmadocInventoryReceipt::plan($order), 'key');
    $user = User::factory()->make();

    expect(fn () => FarmadocInventoryReceipt::register($order, (int) $ubication->id, [
        $keys[0] => ['target' => 'new', 'quantity' => 3],
        $keys[1] => ['target' => 'new', 'quantity' => 1],
    ], $user))->toThrow(InvalidArgumentException::class, 'debe estar entre 0 y 2')
        ->and(fn () => FarmadocInventoryReceipt::register($order, (int) $ubication->id, [
            $keys[0] => ['target' => 'product:'.$product->id, 'quantity' => 1],
            $keys[1] => ['target' => 'product:'.$product->id, 'quantity' => 1],
        ], $user))->toThrow(InvalidArgumentException::class, 'Eligió el mismo producto')
        ->and(fn () => FarmadocInventoryReceipt::register($order, (int) $ubication->id, [
            $keys[0] => ['target' => null, 'quantity' => 0],
            $keys[1] => ['target' => null, 'quantity' => 0],
        ], $user))->toThrow(InvalidArgumentException::class, 'al menos una unidad')
        ->and(fn () => FarmadocInventoryReceipt::register($order, 999999, [], $user))
        ->toThrow(InvalidArgumentException::class, 'ubicación activa')
        ->and($order->refresh()->status)->toBe(FarmadocOrder::STATUS_SENT);
});

it('la entrada es atómica, con el pedido bloqueado, y queda auditada', function (): void {
    $source = file_get_contents(receiptRoot().'/app/Support/Farmadoc/FarmadocInventoryReceipt.php');

    expect($source)->toContain('DB::transaction(')
        ->and($source)->toContain('->lockForUpdate()->firstOrFail()')
        ->and($source)->toContain('$locked->canBeReceived()')
        ->and($source)->toContain("SecurityAudit::log('AUDIT_FARMADOC_ORDER_RECEIVED_INTO_INVENTORY'")
        ->and($source)->toContain('$receipt[\'previous\'] = self::snapshot($product);')
        ->and($source)->toContain("self::ENTRY_TYPE_PREFIX.\$locked->order_number");
});

it('el botón, la nota de recibido y la ubicación del pedido quedan en las pantallas', function (): void {
    $root = receiptRoot();
    $view = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Pages/ViewFarmadocOrder.php');
    $action = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Actions/ReceiveFarmadocOrderAction.php');
    $page = file_get_contents($root.'/app/Filament/Operations/Pages/FarmadocMedicationOrder.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $infolist = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Schemas/FarmadocOrderInfolist.php');
    $adjuster = file_get_contents($root.'/app/Services/OperationInventoryProductStockAdjuster.php');

    expect($view)->toContain('ReceiveFarmadocOrderAction::make()')
        ->and($view)->toContain('FarmadocOrderActions::viewReceiptNote()')
        ->and($action)->toContain("->label('Dar entrada a inventario')")
        ->and($action)->toContain('$record->canBeReceived() && self::userCanReceive()')
        ->and($action)->toContain('OperationInventoryResource::canAccess()')
        ->and($page)->toContain("ToggleButtons::make('ubication_id')")
        ->and($page)->toContain('$ubicationId,')
        ->and($routes)->toContain("->name('operations.farmadoc-orders.receipt-note')")
        ->and($infolist)->toContain("Section::make('Entrada a inventario')")
        ->and($adjuster)->toContain('?string $entryType = null');
});

it('Farmadoc consulta la recepción en el API de aliados con su propio permiso', function (): void {
    $root = receiptRoot();
    $routes = file_get_contents($root.'/routes/partners-api.php');
    $docs = file_get_contents($root.'/resources/views/api-partners/documentation.blade.php');

    expect(ApiPartnerAbility::FarmadocOrdersRead->value)->toBe('farmadoc.orders.read')
        ->and(ApiPartnerAbility::options())->toHaveKey('farmadoc.orders.read')
        ->and($routes)->toContain("Route::get('pedidos-farmadoc/{orderNumber}/nota-de-recibido'")
        ->and(substr_count($routes, "ApiPartnerAbility::FarmadocOrdersRead->value"))->toBe(3)
        ->and($docs)->toContain('id="pedidos-farmadoc"')
        ->and($docs)->toContain('/pedidos-farmadoc/{numero}/nota-de-recibido');
});
