<?php

declare(strict_types=1);

use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocMedicationList;
use App\Support\Farmadoc\FarmadocOrderDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('el catálogo de Las Delicias no conserva precio ni existencia', function (): void {
    $medications = FarmadocMedicationList::fromApiRows([
        [
            'product_id' => 15,
            'name' => 'Acetaminofen 500 mg',
            'sku' => 'MED-500-A',
            'sale_price' => 1200.5,
            'quantity' => 100,
            'available_quantity' => 95,
            'cost' => 10,
            'active_ingredient' => ['Paracetamol'],
            'concentration' => '500 mg',
            'presentation' => 'Tableta',
            'presentation_type' => 'Caja',
            'requires_prescription' => false,
        ],
        ['product_id' => 0, 'name' => 'Inválido'],
    ]);

    expect($medications)->toHaveCount(1)
        ->and($medications[0])->not->toHaveKeys(['sale_price', 'quantity', 'available_quantity', 'cost'])
        ->and($medications[0]['active_ingredient'])->toBe('Paracetamol')
        ->and($medications[0]['presentation'])->toBe('Tableta · Caja')
        ->and($medications[0]['formula_label'])->toBe('Venta libre');
});

it('filtra el catálogo por principio activo y pagina', function (): void {
    $rows = [];

    for ($id = 1; $id <= 12; $id++) {
        $rows[] = [
            'product_id' => $id,
            'name' => $id === 7 ? 'Ibuprofeno' : 'Producto '.$id,
            'active_ingredient' => $id === 7 ? ['Ibuprofeno'] : ['Otro'],
        ];
    }

    $catalog = FarmadocMedicationList::fromApiRows($rows);
    $page = FarmadocMedicationList::paginate($catalog, 'ibuprofeno', 1, 10);

    expect($page->total())->toBe(1)
        ->and($page->items()[0]['product_id'])->toBe(7);

    $all = FarmadocMedicationList::paginate($catalog, null, 2, 10);

    expect($all->total())->toBe(12)
        ->and($all->items())->toHaveCount(2);
});

it('arma el pedido con unit_price 0 y rechaza un medicamento fuera del catálogo', function (): void {
    $catalog = FarmadocMedicationList::fromApiRows([
        ['product_id' => 15, 'name' => 'Acetaminofen', 'sku' => 'MED'],
    ]);

    $items = FarmadocOrderDraft::items([
        ['product_id' => 15, 'quantity' => 2],
        ['product_id' => 15, 'quantity' => 3],
    ], $catalog);

    expect($items)->toHaveCount(1)
        ->and($items[0]['quantity'])->toBe(5)
        ->and($items[0]['unit_price'])->toBe(0.0)
        ->and($items[0]['product_name_snapshot'])->toBe('Acetaminofen');

    expect(fn () => FarmadocOrderDraft::items([
        ['product_id' => 99, 'quantity' => 1],
    ], $catalog))->toThrow(FarmadocException::class);
});

it('la pantalla y el cliente no consultan inventario por sucursal ni muestran precio', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/FarmadocMedicationOrder.php');
    $client = file_get_contents(dirname(__DIR__, 2).'/app/Services/Farmadoc/FarmadocAllyClient.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    $stepper = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/columns/farmadoc-quantity-stepper.blade.php');

    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/pages/farmadoc-medication-order.blade.php');

    expect($page)
        ->not->toContain('inventory-by-branch')
        ->not->toContain('sale_price')
        ->not->toContain('addToOrder')
        ->toContain('farmadoc-quantity-stepper')
        ->toContain('changeLineQuantity')
        ->toContain('reviewingOrder')
        ->toContain('Validar pedido')
        ->toContain('No se muestran precios ni existencias.');

    expect($view)
        ->toContain('@if ($reviewingOrder)')
        ->toContain('wire:click="reviewOrder"')
        ->toContain('Pedido por validar')
        ->toContain('x-filament-actions::modals')
        ->toContain('Disminuir cantidad')
        ->toContain('Aumentar cantidad')
        ->toContain('unidades')
        ->not->toContain('sale_price');

    expect($stepper)
        ->toContain('Aumentar cantidad')
        ->toContain('Disminuir cantidad')
        ->toContain('rounded-full');

    expect($client)
        ->toContain('/api/external/inventory/las-delicias')
        ->toContain("'unit_price' => 0.0")
        ->not->toContain('inventory-by-branch');

    expect($form)
        ->toContain('FarmadocMedicationOrder::getUrl')
        ->not->toContain('El pedido al proveedor se conecta en el paso siguiente.');
});

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('envía el pedido a Farmadoc con precio cero y descarta el precio del catálogo', function (): void {
    config()->set('services.farmadoc', [
        'base_url' => 'https://farmasysdoc.test',
        'token' => 'fd_test',
        'partner_company' => 'ALDO-2026-0021',
        'client_id' => 12,
        'timeout' => 5,
    ]);

    Http::fake([
        'https://farmasysdoc.test/api/external/status' => Http::response(['status' => 'active']),
        'https://farmasysdoc.test/api/external/inventory/las-delicias*' => Http::response([
            'logo_url' => 'https://farmasysdoc.test/logo.png',
            'branch' => ['id' => 3, 'name' => 'Las Delicias'],
            'data' => [[
                'product_id' => 15,
                'name' => 'Acetaminofen',
                'sku' => 'MED',
                'sale_price' => 99,
                'quantity' => 10,
                'active_ingredient' => ['Paracetamol'],
            ]],
        ]),
        'https://farmasysdoc.test/api/external/orders' => Http::response([
            'message' => 'Pedido creado correctamente.',
            'data' => [
                'order_number' => 'IC-1',
                'status' => 'pendiente',
                'items_count' => 1,
                'total' => 0,
            ],
        ], 201),
    ]);

    $client = new FarmadocAllyClient;
    $catalog = $client->catalog();

    expect($catalog['medications'][0])->not->toHaveKey('sale_price')
        ->and($catalog['branch_id'])->toBe(3);

    $items = FarmadocOrderDraft::items([
        ['product_id' => 15, 'quantity' => 2, 'unit_price' => 99],
    ], $catalog['medications']);

    $order = $client->placeOrder($items, $catalog['branch_id'], 'Pedido de prueba', 'IC-1');

    expect($order['order_number'])->toBe('IC-1')
        ->and($order['status_label'])->toBe('Pendiente')
        ->and(array_key_exists('total', $order))->toBeFalse();

    Http::assertSent(function ($request): bool {
        if (! str_ends_with((string) $request->url(), '/api/external/orders')) {
            return false;
        }

        $body = $request->data();

        return ($body['items'][0]['unit_price'] ?? null) == 0
            && ($body['partner_company'] ?? null) === 'ALDO-2026-0021'
            && (int) ($body['client_id'] ?? 0) === 12
            && (int) ($body['branch_id'] ?? 0) === 3
            && ! array_key_exists('sale_price', $body['items'][0]);
    });
});
