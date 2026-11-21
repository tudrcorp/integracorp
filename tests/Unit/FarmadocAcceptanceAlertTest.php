<?php

declare(strict_types=1);

use App\Models\FarmadocOrder;
use App\Models\User;
use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocAcceptanceAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();

    config()->set('services.farmadoc', [
        'base_url' => 'https://farmasysdoc.test',
        'token' => 'fd_test',
        'partner_company' => 'ALDO-2026-0021',
        'client_id' => 12,
        'timeout' => 5,
    ]);

    (new ReflectionProperty(FarmadocAcceptanceAlerts::class, 'tablesExist'))->setValue(null, true);
});

afterEach(function (): void {
    FarmadocAcceptanceAlerts::flushTableCache();
    DB::rollBack();
});

function acceptanceAlertUser(string $email, array $departments, ?int $supplierId = null): User
{
    $user = User::factory()->make(['email' => $email, 'departament' => $departments]);
    $user->supplier_id = $supplierId;

    return $user;
}

it('solo los analistas TDG de Operaciones y los superadmin reciben el aviso', function (string $email, array $departments, ?int $supplierId, bool $expected): void {
    expect(FarmadocAcceptanceAlerts::userCanReceive(acceptanceAlertUser($email, $departments, $supplierId)))->toBe($expected);
})->with([
    'analista de operaciones' => ['ana@tudrencasa.com', ['OPERACIONES'], null, true],
    'superadmin' => ['jefe@tudrencasa.com', ['SUPERADMIN'], null, true],
    'atención médica' => ['med@tudrencasa.com', ['OPERACIONES', 'ATENMEDI'], null, false],
    'correo externo' => ['ana@gmail.com', ['OPERACIONES'], null, false],
    'usuario de proveedor' => ['prov@tudrencasa.com', ['OPERACIONES'], 7, false],
    'otro departamento' => ['neg@tudrencasa.com', ['NEGOCIOS'], null, false],
]);

it('sin las migraciones el aviso no existe ni rompe el panel', function (): void {
    FarmadocAcceptanceAlerts::flushTableCache();

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Farmadoc/FarmadocAcceptanceAlerts.php');

    expect($source)->toContain("Schema::hasTable('farmadoc_order_acceptance_dismissals')")
        ->and($source)->toContain('FarmadocOrder::acceptanceDetailColumnsExist()');
});

it('el detalle de la aceptación guarda cantidades y rechazos, nunca precios', function (): void {
    Http::fake([
        'https://farmasysdoc.test/api/external/orders/IC-ACC*' => Http::response([
            'data' => [
                'order_number' => 'IC-ACC',
                'status' => 'aceptado',
                'updated_at' => '2026-11-20T18:21:15-04:00',
                'acceptance' => [
                    'delivery_note_number' => 'NEP-0000002',
                    'accepted_at' => '2026-11-20T18:21:15-04:00',
                    'accepted_by' => 'Operador Farmadoc',
                    'accepted_total_usd' => 44.18,
                    'items' => [
                        ['product_name' => '20/20 SOL ACOND 120ML', 'barcode' => '7591929232021', 'sku' => 'CSV-1', 'quantity_ordered' => 2, 'quantity_on_hand' => -2, 'accepted' => true, 'rejection_note' => null, 'unit_price_usd' => 14.78],
                        ['product_name' => 'IBUPROFENO', 'barcode' => null, 'quantity_ordered' => '1', 'accepted' => false, 'rejection_note' => 'Sin existencia', 'unit_price_usd' => 3.5],
                        'basura',
                    ],
                ],
            ],
        ]),
    ]);

    $remote = (new FarmadocAllyClient)->orderStatus('IC-ACC');

    expect($remote['accepted_by'])->toBe('Operador Farmadoc')
        ->and($remote['acceptance_items'])->toBe([
            ['name' => '20/20 SOL ACOND 120ML', 'barcode' => '7591929232021', 'sku' => 'CSV-1', 'quantity' => 2, 'accepted' => true, 'rejection_note' => null],
            ['name' => 'IBUPROFENO', 'barcode' => null, 'sku' => null, 'quantity' => 1, 'accepted' => false, 'rejection_note' => 'Sin existencia'],
        ])
        ->and(json_encode($remote))->not->toContain('14.78')
        ->and(json_encode($remote))->not->toContain('44.18')
        ->and(json_encode($remote))->not->toContain('quantity_on_hand');
});

it('presenta el pedido aceptado con conteos, rechazos y sin enlaces si el analista no puede abrir pedidos', function (): void {
    $order = new FarmadocOrder([
        'order_number' => 'IC-ACC',
        'service_order_number' => 'ORD-003',
        'delivery_note_number' => 'NEP-0000002',
        'accepted_by' => 'Operador Farmadoc',
        'accepted_at' => '2026-11-20 18:21:15',
        'acceptance_items' => [
            ['name' => 'A', 'barcode' => '759', 'quantity' => 2, 'accepted' => true, 'rejection_note' => null],
            ['name' => 'B', 'barcode' => null, 'quantity' => 1, 'accepted' => false, 'rejection_note' => 'Sin existencia'],
        ],
    ]);
    $order->id = 99;

    $alert = FarmadocAcceptanceAlerts::present($order, canOpenOrders: false);

    expect($alert['accepted_count'])->toBe(1)
        ->and($alert['rejected_count'])->toBe(1)
        ->and($alert['coordination_reference'])->toBe('Dpto. Operaciones TDG')
        ->and($alert['accepted_at'])->toBe('20/11/2026 06:21 pm')
        ->and($alert['items'][1]['rejection_note'])->toBe('Sin existencia')
        ->and($alert['order_url'])->toBeNull()
        ->and($alert['delivery_note_url'])->toBeNull();
});

it('sin detalle de Farmadoc muestra las líneas del pedido como aceptadas', function (): void {
    $order = new FarmadocOrder([
        'order_number' => 'IC-OLD',
        'items' => [['product_id' => 1, 'name' => 'Acetaminofen', 'sku' => null, 'quantity' => 3]],
    ]);

    expect(FarmadocAcceptanceAlerts::items($order))->toBe([
        ['name' => 'Acetaminofen', 'barcode' => null, 'quantity' => 3, 'accepted' => true, 'rejection_note' => null],
    ]);
});

it('el modal vive en todo el panel de Operaciones, se cierra por analista y no muestra montos', function (): void {
    $root = dirname(__DIR__, 2);
    $provider = file_get_contents($root.'/app/Providers/Filament/OperationsPanelProvider.php');
    $hook = file_get_contents($root.'/resources/views/filament/operations/hooks/farmadoc-acceptance-alert.blade.php');
    $component = file_get_contents($root.'/app/Livewire/Operations/FarmadocAcceptanceAlertModal.php');
    $view = file_get_contents($root.'/resources/views/livewire/operations/farmadoc-acceptance-alert-modal.blade.php');
    $support = file_get_contents($root.'/app/Support/Farmadoc/FarmadocAcceptanceAlerts.php');
    $migration = file_get_contents($root.'/database/migrations/2026_11_20_200000_create_farmadoc_order_acceptance_dismissals_table.php');
    $console = file_get_contents($root.'/routes/console.php');

    expect($provider)->toContain("view('filament.operations.hooks.farmadoc-acceptance-alert')")
        ->and($hook)->toContain('FarmadocAcceptanceAlerts::userCanReceive(auth()->user())')
        ->and($hook)->toContain('@persist(')
        ->and($component)->toContain('public const POLL_SECONDS = 15;')
        ->and($component)->toContain('#[Locked]')
        ->and($component)->toContain('$this->skipRender()')
        ->and($view)->toContain('Farmadoc aceptó el pedido')
        ->and($view)->toContain('Entendido')
        ->and($view)->toContain('openOrder(')
        ->and($view)->not->toContain('usd')
        ->and($view)->not->toContain('price')
        ->and($support)->toContain('insertOrIgnore(')
        ->and($support)->toContain('->sendToDatabase($user)')
        ->and($support)->toContain('public const WINDOW_HOURS = 24;')
        ->and($migration)->toContain("unique(['farmadoc_order_id', 'user_id']")
        ->and($console)->toContain('->everyFiveMinutes()');
});

it('un pedido sin coordinación muestra «Dpto. Operaciones TDG» como origen', function (): void {
    $root = dirname(__DIR__, 2);
    $table = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Tables/FarmadocOrdersTable.php');
    $infolist = file_get_contents($root.'/app/Filament/Operations/Resources/FarmadocOrders/Schemas/FarmadocOrderInfolist.php');

    expect(FarmadocOrder::DEFAULT_ORIGIN_LABEL)->toBe('Dpto. Operaciones TDG')
        ->and($table)->toContain('->placeholder(FarmadocOrder::DEFAULT_ORIGIN_LABEL)')
        ->and($table)->not->toContain('Sin coordinación')
        ->and($infolist)->toContain('->placeholder(FarmadocOrder::DEFAULT_ORIGIN_LABEL)');
});
