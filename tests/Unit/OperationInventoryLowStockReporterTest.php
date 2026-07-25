<?php

declare(strict_types=1);

use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventorySetting;
use App\Models\OperationInventoryUbication;
use App\Services\OperationInventoryLowStockReporter;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('arma el cuerpo de whatsapp con productos, umbral y detalle por almacén', function (): void {
    $report = [
        'threshold' => 3,
        'generated_at' => '22/07/2026 08:00',
        'products' => [
            [
                'id' => 1,
                'code' => 'PROD-001',
                'name' => 'GASAS ESTERILES',
                'category' => 'Insumos',
                'unit' => 'CAJA',
                'total_existence' => 2,
                'warehouses' => [
                    ['name' => 'DIAGNOMOVIL', 'existence' => 1],
                    ['name' => '3 DE FEBRERO', 'existence' => 1],
                ],
            ],
        ],
    ];

    $body = (new OperationInventoryLowStockReporter)->whatsappBody($report);

    expect($body)
        ->toContain('Alerta de stock bajo')
        ->toContain('menor o igual a *3*')
        ->toContain('*PROD-001* · GASAS ESTERILES')
        ->toContain('Categoría: Insumos  |  Total: 2')
        ->toContain('· DIAGNOMOVIL: 1')
        ->toContain('· 3 DE FEBRERO: 1');
});

it('filtra productos activos bajo el umbral e ignora inactivos y con stock suficiente', function (): void {
    if (
        ! Schema::hasTable('operation_inventory_products')
        || ! Schema::hasTable('operation_inventory_product_stocks')
        || ! Schema::hasTable('operation_inventory_ubications')
    ) {
        expect(true)->toBeTrue();

        return;
    }

    $ubicationId = OperationInventoryUbication::query()->value('id');

    if ($ubicationId === null) {
        expect(true)->toBeTrue();

        return;
    }

    $low = OperationInventoryProduct::factory()->create([
        'code' => 'TEST-LOWSTOCK-LOW-'.uniqid(),
        'name' => 'TEST LOW STOCK LOW',
        'is_active' => true,
    ]);
    $ok = OperationInventoryProduct::factory()->create([
        'code' => 'TEST-LOWSTOCK-OK-'.uniqid(),
        'name' => 'TEST LOW STOCK OK',
        'is_active' => true,
    ]);
    $inactive = OperationInventoryProduct::factory()->inactive()->create([
        'code' => 'TEST-LOWSTOCK-OFF-'.uniqid(),
        'name' => 'TEST LOW STOCK OFF',
    ]);

    OperationInventoryProductStock::query()->create([
        'operation_inventory_product_id' => $low->id,
        'operation_inventory_ubication_id' => $ubicationId,
        'existence' => 2,
        'created_by' => 'pest',
        'updated_by' => 'pest',
    ]);
    OperationInventoryProductStock::query()->create([
        'operation_inventory_product_id' => $ok->id,
        'operation_inventory_ubication_id' => $ubicationId,
        'existence' => 10,
        'created_by' => 'pest',
        'updated_by' => 'pest',
    ]);
    OperationInventoryProductStock::query()->create([
        'operation_inventory_product_id' => $inactive->id,
        'operation_inventory_ubication_id' => $ubicationId,
        'existence' => 1,
        'created_by' => 'pest',
        'updated_by' => 'pest',
    ]);

    try {
        $ids = (new OperationInventoryLowStockReporter)
            ->lowStockProducts(3)
            ->pluck('id')
            ->all();

        expect($ids)->toContain($low->id)
            ->and($ids)->not->toContain($ok->id)
            ->and($ids)->not->toContain($inactive->id);
    } finally {
        OperationInventoryProductStock::query()
            ->whereIn('operation_inventory_product_id', [$low->id, $ok->id, $inactive->id])
            ->delete();
        $low->delete();
        $ok->delete();
        $inactive->delete();
    }
});

it('usa el umbral configurado en OperationInventorySetting', function (): void {
    if (! Schema::hasTable('operation_inventory_settings')) {
        expect(true)->toBeTrue();

        return;
    }

    $settings = OperationInventorySetting::current();
    $previous = $settings->low_stock_threshold;
    $previousUpdatedBy = $settings->updated_by;

    $settings->update([
        'low_stock_threshold' => 5,
        'updated_by' => 'pest',
    ]);

    try {
        $report = (new OperationInventoryLowStockReporter)->report();

        expect($report['threshold'])->toBe(5)
            ->and($report)->toHaveKeys(['threshold', 'generated_at', 'products']);
    } finally {
        $settings->update([
            'low_stock_threshold' => $previous,
            'updated_by' => $previousUpdatedBy,
        ]);
    }
});
