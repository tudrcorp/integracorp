<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationInventoryProduct;
use App\Models\OperationInventorySetting;
use Illuminate\Support\Collection;

class OperationInventoryLowStockReporter
{
    /**
     * @return array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }
     */
    public function report(?int $threshold = null): array
    {
        $threshold ??= OperationInventorySetting::current()->lowStockThreshold();

        $products = $this->lowStockProducts($threshold)
            ->map(fn (OperationInventoryProduct $product): array => $this->mapProduct($product))
            ->values()
            ->all();

        return [
            'threshold' => $threshold,
            'generated_at' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'products' => $products,
        ];
    }

    /**
     * @return Collection<int, OperationInventoryProduct>
     */
    public function lowStockProducts(int $threshold): Collection
    {
        return OperationInventoryProduct::query()
            ->where('is_active', true)
            ->with(['category', 'stocks.ubication'])
            ->withSum('stocks', 'existence')
            ->havingRaw('COALESCE(stocks_sum_existence, 0) <= ?', [$threshold])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }|null
     */
    public function reportForProduct(int $productId, ?int $threshold = null): ?array
    {
        $threshold ??= OperationInventorySetting::current()->lowStockThreshold();

        $product = OperationInventoryProduct::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->with(['category', 'stocks.ubication'])
            ->withSum('stocks', 'existence')
            ->first();

        if ($product === null) {
            return null;
        }

        $total = (int) ($product->stocks_sum_existence ?? $product->stocks->sum('existence'));

        if ($total > $threshold) {
            return null;
        }

        return [
            'threshold' => $threshold,
            'generated_at' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'products' => [$this->mapProduct($product)],
        ];
    }

    /**
     * @param  array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }  $report
     */
    public function whatsappBody(array $report): string
    {
        $threshold = $report['threshold'];
        $date = $report['generated_at'];
        $count = count($report['products']);

        $lines = [
            '*INTEGRACORP · Alerta de stock bajo*',
            "Fecha y hora: {$date}",
            '',
            "Productos activos con existencia total menor o igual a *{$threshold}*: *{$count}*",
            '',
        ];

        foreach ($report['products'] as $product) {
            $category = $product['category'] ?? 'Sin categoría';
            $lines[] = "*{$product['code']}* · {$product['name']}";
            $lines[] = "Categoría: {$category}  |  Total: {$product['total_existence']}";

            if ($product['warehouses'] === []) {
                $lines[] = '  · Sin stock registrado en almacenes';
            } else {
                foreach ($product['warehouses'] as $warehouse) {
                    $lines[] = "  · {$warehouse['name']}: {$warehouse['existence']}";
                }
            }

            $lines[] = '';
        }

        $lines[] = '──────────────';
        $lines[] = 'La alerta se enviará diariamente hasta que la existencia total sea mayor al umbral.';

        return rtrim(implode(PHP_EOL, $lines));
    }

    /**
     * @param  array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }  $report
     */
    public function whatsappBodyImmediate(array $report): string
    {
        $threshold = $report['threshold'];
        $date = $report['generated_at'];
        $product = $report['products'][0] ?? null;

        if ($product === null) {
            return '';
        }

        $category = $product['category'] ?? 'Sin categoría';
        $lines = [
            '*INTEGRACORP · Alerta inmediata de stock bajo*',
            "Fecha y hora: {$date}",
            '',
            "El producto quedó con existencia total menor o igual a *{$threshold}*.",
            '',
            "*{$product['code']}* · {$product['name']}",
            "Categoría: {$category}  |  Total: {$product['total_existence']}",
        ];

        if ($product['warehouses'] === []) {
            $lines[] = '  · Sin stock registrado en almacenes';
        } else {
            foreach ($product['warehouses'] as $warehouse) {
                $lines[] = "  · {$warehouse['name']}: {$warehouse['existence']}";
            }
        }

        $lines[] = '';
        $lines[] = '──────────────';
        $lines[] = 'También recibirá el resumen diario mientras el producto siga bajo el umbral.';

        return rtrim(implode(PHP_EOL, $lines));
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     category: string|null,
     *     unit: string|null,
     *     total_existence: int,
     *     warehouses: list<array{name: string, existence: int}>
     * }
     */
    private function mapProduct(OperationInventoryProduct $product): array
    {
        $warehouses = $product->stocks
            ->map(fn ($stock): array => [
                'name' => (string) ($stock->ubication?->name ?? 'Sin almacén'),
                'existence' => (int) $stock->existence,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'id' => (int) $product->id,
            'code' => (string) $product->code,
            'name' => (string) $product->name,
            'category' => $product->category?->name,
            'unit' => $product->unit,
            'total_existence' => (int) ($product->stocks_sum_existence ?? $product->stocks->sum('existence')),
            'warehouses' => $warehouses,
        ];
    }
}
