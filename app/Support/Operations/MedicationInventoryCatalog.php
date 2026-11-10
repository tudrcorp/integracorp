<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventory;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use Illuminate\Support\Collection;

/**
 * Búsqueda de medicamentos en el inventario Diagnomóvil para facturarlos al paciente.
 *
 * Solo entran productos activos de la categoría «Medicamentos». La existencia vendible de un
 * producto en un almacén es la MENOR entre las dos tablas que la registran
 * (`operation_inventories` y `operation_inventory_product_stocks`): hoy no siempre coinciden
 * y vender por encima de cualquiera de las dos dejaría una negativa.
 */
final class MedicationInventoryCatalog
{
    public const MEDICATION_CATEGORY_NAME = 'MEDICAMENTOS';

    public const SEARCH_LIMIT = 25;

    /**
     * Palabras que no identifican un medicamento y no deben pesar en la búsqueda.
     */
    private const NOISE_TOKENS = [
        'TABLETAS', 'TABLETA', 'TAB', 'TABS', 'CAPSULAS', 'CAPSULA', 'CAP', 'CAPS', 'JARABE', 'GOTAS',
        'AMP', 'AMPOLLA', 'AMPOLLAS', 'SUSPENSION', 'CREMA', 'UNGUENTO', 'SOLUCION', 'INYECTABLE', 'SOBRES',
        'CAJA', 'FRASCO', 'UNIDAD', 'UNIDADES', 'CON', 'DE', 'DEL', 'LOS', 'LAS', 'PARA', 'MG', 'ML', 'GR',
    ];

    /**
     * Concentración o cantidad («100MG», «500», «0,9%»): no identifica el medicamento.
     */
    public static function isDoseToken(string $token): bool
    {
        return preg_match('/^\d+([.,]\d+)?(MG|MCG|G|GR|ML|UI|%)?$/u', $token) === 1;
    }

    public static function medicationCategoryId(): ?int
    {
        return once(static function (): ?int {
            $id = OperationInventoryProductCategory::query()
                ->whereRaw('UPPER(TRIM(name)) = ?', [self::MEDICATION_CATEGORY_NAME])
                ->value('id');

            return $id !== null ? (int) $id : null;
        });
    }

    /**
     * Palabras significativas del texto buscado, en mayúsculas y sin duplicados.
     *
     * @return list<string>
     */
    public static function tokens(string $term): array
    {
        $normalized = mb_strtoupper(trim($term));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_values(array_unique(array_filter(
            $parts,
            fn (string $part): bool => mb_strlen($part) >= 3 && ! in_array($part, self::NOISE_TOKENS, true),
        )));

        if ($tokens === [] && $parts !== []) {
            // Solo había palabras genéricas: se busca por la más larga para no dejar la lista vacía.
            usort($parts, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $tokens = [$parts[0]];
        }

        return array_slice($tokens, 0, 6);
    }

    /**
     * Productos de la categoría Medicamentos que contienen alguna palabra del texto, ordenados
     * por cuántas palabras coinciden (la primera, normalmente la marca, pesa doble).
     *
     * @return Collection<int, OperationInventoryProduct>
     */
    public static function search(string $term, int $limit = self::SEARCH_LIMIT): Collection
    {
        $categoryId = self::medicationCategoryId();
        $tokens = self::tokens($term);

        if ($categoryId === null || $tokens === []) {
            return collect();
        }

        $candidates = OperationInventoryProduct::query()
            ->with('category:id,name,profit_percentage')
            ->where('operation_inventory_product_category_id', $categoryId)
            ->where('is_active', true)
            ->where(function ($query) use ($tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhere('name', 'like', '%'.addcslashes($token, '%_\\').'%')
                        ->orWhere('code', 'like', '%'.addcslashes($token, '%_\\').'%');
                }
            })
            ->limit(200)
            ->get(['id', 'operation_inventory_product_category_id', 'code', 'name', 'cost', 'unit', 'is_active']);

        $nameTokens = array_values(array_filter($tokens, fn (string $token): bool => ! self::isDoseToken($token)));

        return $candidates
            ->map(function (OperationInventoryProduct $product) use ($tokens, $nameTokens): array {
                $haystack = mb_strtoupper($product->name.' '.$product->code);
                $score = 0;
                $matchesName = $nameTokens === [];

                foreach ($tokens as $index => $token) {
                    if (! str_contains($haystack, $token)) {
                        continue;
                    }

                    $score += $index === 0 ? 2 : 1;
                    $matchesName = $matchesName || in_array($token, $nameTokens, true);
                }

                // La dosis sola («500MG») no identifica el medicamento: solo ordena.
                return ['product' => $product, 'score' => $matchesName ? $score : 0];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sort(fn (array $a, array $b): int => [$b['score'], (string) $a['product']->name] <=> [$a['score'], (string) $b['product']->name])
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /**
     * Resumen de lo que el inventario tiene para un nombre recetado, para decidir antes de
     * seleccionar: cuántas coincidencias hay, cuántas se pueden facturar ya (con precio y
     * existencia) y cuántas unidades suman esas facturables. Una sola consulta por nombre y
     * petición.
     *
     * «sellable» cuenta los que se pueden elegir (con existencia), aunque falte cargarles el
     * costo; «without_cost» cuántos de ellos lo necesitan.
     *
     * @return array{matches: int, sellable: int, units: int, without_cost: int}
     */
    public static function searchSummary(string $term): array
    {
        $normalized = mb_strtoupper(trim($term));

        return once(static function () use ($normalized): array {
            $products = self::search($normalized, 200);

            if ($products->isEmpty()) {
                return ['matches' => 0, 'sellable' => 0, 'units' => 0, 'without_cost' => 0];
            }

            $availability = self::availabilityByProduct($products->pluck('id')->all());
            $sellable = 0;
            $units = 0;
            $withoutCost = 0;

            foreach ($products as $product) {
                $available = (int) array_sum(array_column($availability[(int) $product->id] ?? [], 'available'));

                if (self::unsellableReason($product, $available, allowMissingCost: true) === null) {
                    $sellable++;
                    $units += $available;
                    $withoutCost += self::needsCost($product) ? 1 : 0;
                }
            }

            return ['matches' => $products->count(), 'sellable' => $sellable, 'units' => $units, 'without_cost' => $withoutCost];
        });
    }

    /**
     * Existencia vendible por almacén de cada producto.
     *
     * @param  list<int>  $productIds
     * @return array<int, array<int, array{ubication_id: int, ubication_name: string, available: int, operation_inventory_id: int}>>
     */
    public static function availabilityByProduct(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return [];
        }

        $inventories = OperationInventory::query()
            ->whereIn('operation_inventory_product_id', $productIds)
            ->whereNotNull('operation_inventory_ubication_id')
            ->get(['id', 'operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence']);

        $stocks = OperationInventoryProductStock::query()
            ->whereIn('operation_inventory_product_id', $productIds)
            ->get(['operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence'])
            ->keyBy(fn (OperationInventoryProductStock $stock): string => $stock->operation_inventory_product_id.':'.$stock->operation_inventory_ubication_id);

        $ubications = OperationInventoryUbication::query()
            ->where('is_active', true)
            ->pluck('name', 'id');

        $result = [];

        foreach ($inventories as $inventory) {
            $ubicationId = (int) $inventory->operation_inventory_ubication_id;

            if (! $ubications->has($ubicationId)) {
                continue;
            }

            $stock = $stocks->get($inventory->operation_inventory_product_id.':'.$ubicationId);
            $available = self::sellableExistence($inventory->existence, $stock?->existence);

            if ($available <= 0) {
                continue;
            }

            $result[(int) $inventory->operation_inventory_product_id][$ubicationId] = [
                'ubication_id' => $ubicationId,
                'ubication_name' => (string) $ubications->get($ubicationId),
                'available' => $available,
                'operation_inventory_id' => (int) $inventory->id,
            ];
        }

        return $result;
    }

    /**
     * La menor de las dos existencias; si falta el registro por almacén, no hay venta posible.
     */
    public static function sellableExistence(mixed $inventoryExistence, mixed $stockExistence): int
    {
        if ($stockExistence === null) {
            return 0;
        }

        return max(0, min((int) $inventoryExistence, (int) $stockExistence));
    }

    public static function totalAvailable(int $productId): int
    {
        return (int) array_sum(array_column(self::availabilityByProduct([$productId])[$productId] ?? [], 'available'));
    }

    /**
     * Precio de venta vigente; null si el producto no puede facturarse por precio.
     */
    public static function sellingPrice(OperationInventoryProduct $product, ?float $costOverride = null): ?float
    {
        $price = $costOverride !== null
            ? OperationInventoryProductPricing::finalPrice($costOverride, OperationInventoryProductPricing::profitPercentageFor($product))
            : OperationInventoryProductPricing::finalPriceFor($product);

        return $price !== null && $price > 0 ? $price : null;
    }

    /**
     * Producto sin costo cargado: el analista puede cargarlo al facturar.
     */
    public static function needsCost(OperationInventoryProduct $product): bool
    {
        return ! is_numeric($product->cost) || (float) $product->cost <= 0;
    }

    /**
     * Opciones del buscador: id => etiqueta con código, existencia y precio.
     *
     * @param  Collection<int, OperationInventoryProduct>  $products
     * @return array<int, string>
     */
    public static function optionLabels(Collection $products): array
    {
        $availability = self::availabilityByProduct($products->pluck('id')->all());

        return $products
            ->mapWithKeys(function (OperationInventoryProduct $product) use ($availability): array {
                $available = (int) array_sum(array_column($availability[(int) $product->id] ?? [], 'available'));

                return [(int) $product->id => self::optionLabel($product, $available)];
            })
            ->all();
    }

    public const UNAVAILABLE_MARK = '— no disponible';

    /**
     * La opción del buscador está deshabilitada (sin existencia, inactiva o sin % en la categoría).
     */
    public static function labelIsUnavailable(string $label): bool
    {
        return str_contains($label, self::UNAVAILABLE_MARK);
    }

    public static function optionLabel(OperationInventoryProduct $product, int $available): string
    {
        $price = self::sellingPrice($product);
        $parts = [
            trim((string) $product->name),
            filled($product->code) ? 'Cód. '.$product->code : null,
            'Exist. '.$available,
            $price !== null ? 'US$ '.number_format($price, 2, '.', ',') : 'sin costo: cárguelo al elegirlo',
        ];

        $label = implode(' · ', array_filter($parts));

        if (self::unsellableReason($product, $available, allowMissingCost: true) === null) {
            return $label;
        }

        return $label.' '.self::UNAVAILABLE_MARK.($available <= 0 ? ' (sin existencia)' : '');
    }

    /**
     * Motivo por el que el producto no puede facturarse, o null si sí puede.
     */
    public static function unsellableReason(OperationInventoryProduct $product, int $available, bool $allowMissingCost = false): ?string
    {
        if (! $product->is_active) {
            return 'El producto «'.$product->name.'» está inactivo.';
        }

        if ((int) $product->operation_inventory_product_category_id !== (int) self::medicationCategoryId()) {
            return 'El producto «'.$product->name.'» no pertenece a la categoría Medicamentos.';
        }

        if (OperationInventoryProductPricing::profitPercentageFor($product) === null) {
            return 'La categoría «'.($product->category?->name ?? 'Medicamentos').'» no tiene % de utilidad. Defínalo en Categorías antes de facturar.';
        }

        if (! $allowMissingCost && self::sellingPrice($product) === null) {
            return OperationInventoryProductPricing::missingPriceReason($product)
                ?? 'El producto «'.$product->name.'» no tiene precio de venta (costo en 0).';
        }

        if ($available <= 0) {
            return 'El producto «'.$product->name.'» no tiene existencia en ningún almacén.';
        }

        return null;
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, OperationInventoryProduct>
     */
    public static function productsById(array $productIds): Collection
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return collect();
        }

        return OperationInventoryProduct::query()
            ->with('category:id,name,profit_percentage')
            ->whereIn('id', $productIds)
            ->get(['id', 'operation_inventory_product_category_id', 'code', 'name', 'cost', 'unit', 'is_active'])
            ->keyBy('id');
    }
}
