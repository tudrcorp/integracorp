<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventory;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Estado de un medicamento en el inventario Diagnomóvil, tal como se informa a los aliados.
 * Nunca expone cantidades, solo el estado:
 *
 * - NO_EXISTE: no hay producto que coincida, o la existencia vendible es 0.
 * - EXISTENCIA_MINIMA: hay existencia, pero no supera el stock mínimo registrado.
 * - DISPONIBLE: la existencia supera el stock mínimo.
 *
 * La existencia es la misma que se usa al facturar ({@see MedicationInventoryCatalog::sellableExistence()}),
 * sumando los almacenes activos; el mínimo es la suma de `min_stock` de esos almacenes.
 *
 * Si el medicamento está vinculado a un ítem de inventario, se usa ese producto. Si no, se busca
 * por nombre en el catálogo y solo cuentan los productos que contienen la palabra principal del
 * nombre recetado (la primera que no es dosis): así «ACIDO FOLICO» no suma existencias de otro ácido.
 */
final class MedicationInventoryStockStatus
{
    public const AVAILABLE = 'DISPONIBLE';

    public const MINIMUM = 'EXISTENCIA_MINIMA';

    public const MISSING = 'NO_EXISTE';

    /** El catálogo cambia poco: el mapeo nombre → productos se cachea; las existencias, nunca. */
    public const NAME_CACHE_SECONDS = 600;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::AVAILABLE, self::MINIMUM, self::MISSING];
    }

    /**
     * Estado de varios medicamentos con un número fijo de consultas de existencia.
     *
     * @param  array<int, array{name: string|null, operation_inventory_id: int|null}>  $medications  por id del medicamento
     * @return array<int, string> estado por id del medicamento
     */
    public static function forMedications(array $medications): array
    {
        return array_map(
            fn (array $detail): string => $detail['status'],
            self::detailsForMedications($medications),
        );
    }

    /**
     * Estado y códigos de barras de los productos del inventario que coinciden con cada medicamento.
     * Los códigos salen de los almacenes activos; puede haber varios (varias presentaciones o marcas)
     * y la lista va vacía si ningún producto coincide.
     *
     * @param  array<int, array{name: string|null, operation_inventory_id: int|null}>  $medications  por id del medicamento
     * @return array<int, array{status: string, barcodes: list<string>}>
     */
    public static function detailsForMedications(array $medications): array
    {
        if ($medications === []) {
            return [];
        }

        $productIdsByMedication = self::productIdsByMedication($medications);
        $allProductIds = array_values(array_unique(array_merge([], ...array_values($productIdsByMedication))));
        [$rowsByProduct, $barcodesByProduct] = self::stockByProduct($allProductIds);

        $details = [];

        foreach ($productIdsByMedication as $medicationId => $productIds) {
            $rows = [];
            $barcodes = [];

            foreach ($productIds as $productId) {
                array_push($rows, ...($rowsByProduct[$productId] ?? []));
                array_push($barcodes, ...($barcodesByProduct[$productId] ?? []));
            }

            $details[$medicationId] = [
                'status' => self::classify(count($productIds), $rows),
                'barcodes' => self::uniqueBarcodes($barcodes),
            ];
        }

        return $details;
    }

    /**
     * Códigos sin espacios, sin vacíos ni repetidos, en orden estable.
     *
     * @param  array<int, mixed>  $barcodes
     * @return list<string>
     */
    public static function uniqueBarcodes(array $barcodes): array
    {
        $clean = [];

        foreach ($barcodes as $barcode) {
            if (! is_scalar($barcode)) {
                continue;
            }

            $barcode = preg_replace('/\s+/', '', (string) $barcode) ?? '';

            if ($barcode !== '') {
                $clean[$barcode] = true;
            }
        }

        $list = array_keys($clean);
        sort($list, SORT_STRING);

        return array_map('strval', $list);
    }

    /**
     * @param  list<array{existence: int, minimum: int}>  $rows
     */
    public static function classify(int $matches, array $rows): string
    {
        if ($matches === 0) {
            return self::MISSING;
        }

        $existence = 0;
        $minimum = 0;

        foreach ($rows as $row) {
            $existence += max(0, (int) $row['existence']);
            $minimum += max(0, (int) $row['minimum']);
        }

        if ($existence <= 0) {
            return self::MISSING;
        }

        return $existence <= $minimum ? self::MINIMUM : self::AVAILABLE;
    }

    /**
     * Palabra que identifica el medicamento: la primera que no es dosis, sin acentos.
     */
    public static function principalToken(string $name): ?string
    {
        foreach (MedicationInventoryCatalog::tokens($name) as $token) {
            if (! MedicationInventoryCatalog::isDoseToken($token)) {
                return self::plain($token);
            }
        }

        return null;
    }

    public static function productMatchesPrincipalToken(string $productName, ?string $principalToken): bool
    {
        if ($principalToken === null) {
            return false;
        }

        return str_contains(self::plain($productName), $principalToken);
    }

    /**
     * @param  array<int, array{name: string|null, operation_inventory_id: int|null}>  $medications
     * @return array<int, list<int>>
     */
    private static function productIdsByMedication(array $medications): array
    {
        $inventoryIds = [];

        foreach ($medications as $medication) {
            if (($medication['operation_inventory_id'] ?? null) !== null) {
                $inventoryIds[] = (int) $medication['operation_inventory_id'];
            }
        }

        $productByInventory = $inventoryIds === []
            ? collect()
            : OperationInventory::query()
                ->whereIn('id', array_values(array_unique($inventoryIds)))
                ->whereNotNull('operation_inventory_product_id')
                ->pluck('operation_inventory_product_id', 'id');

        $byName = [];
        $result = [];

        foreach ($medications as $medicationId => $medication) {
            $inventoryId = $medication['operation_inventory_id'] ?? null;

            if ($inventoryId !== null && $productByInventory->has((int) $inventoryId)) {
                $result[$medicationId] = [(int) $productByInventory->get((int) $inventoryId)];

                continue;
            }

            $name = mb_strtoupper(trim((string) ($medication['name'] ?? '')));
            $byName[$name] ??= $name === '' ? [] : self::productIdsForName($name);
            $result[$medicationId] = $byName[$name];
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private static function productIdsForName(string $name): array
    {
        return Cache::remember(
            'medication-stock-status:name:'.sha1($name),
            self::NAME_CACHE_SECONDS,
            function () use ($name): array {
                $principal = self::principalToken($name);

                return MedicationInventoryCatalog::search($name, 200)
                    ->filter(fn (OperationInventoryProduct $product): bool => self::productMatchesPrincipalToken((string) $product->name, $principal))
                    ->map(fn (OperationInventoryProduct $product): int => (int) $product->getKey())
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * Existencia vendible, mínimo y código de barras por almacén activo de cada producto.
     *
     * @param  list<int>  $productIds
     * @return array{0: array<int, list<array{existence: int, minimum: int}>>, 1: array<int, list<string>>}
     */
    private static function stockByProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [[], []];
        }

        $activeUbicationIds = OperationInventoryUbication::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($activeUbicationIds === []) {
            return [[], []];
        }

        $inventories = OperationInventory::query()
            ->whereIn('operation_inventory_product_id', $productIds)
            ->whereIn('operation_inventory_ubication_id', $activeUbicationIds)
            ->get(['operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence', 'min_stock', 'barcode']);

        $stocks = OperationInventoryProductStock::query()
            ->whereIn('operation_inventory_product_id', $productIds)
            ->whereIn('operation_inventory_ubication_id', $activeUbicationIds)
            ->get(['operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence'])
            ->keyBy(fn (OperationInventoryProductStock $stock): string => $stock->operation_inventory_product_id.':'.$stock->operation_inventory_ubication_id);

        $rows = [];
        $barcodes = [];

        foreach ($inventories as $inventory) {
            if (filled($inventory->barcode)) {
                $barcodes[(int) $inventory->operation_inventory_product_id][] = (string) $inventory->barcode;
            }

            $stock = $stocks->get($inventory->operation_inventory_product_id.':'.$inventory->operation_inventory_ubication_id);

            $rows[(int) $inventory->operation_inventory_product_id][] = [
                'existence' => MedicationInventoryCatalog::sellableExistence($inventory->existence, $stock?->existence),
                'minimum' => (int) $inventory->min_stock,
            ];
        }

        return [$rows, $barcodes];
    }

    private static function plain(string $value): string
    {
        return mb_strtoupper(Str::ascii($value));
    }
}
