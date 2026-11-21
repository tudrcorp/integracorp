<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Filament\Operations\Pages\FarmadocMedicationOrder;
use App\Models\FarmadocOrder;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Líneas de un pedido a Farmadoc listas para mostrar o para dar entrada al inventario.
 *
 * El código de barras se toma, en orden: del pedido (se guarda al enviarlo), del detalle de la
 * aceptación de Farmadoc (por SKU o nombre) y del catálogo de Farmadoc que ya está en cache.
 * Principio activo, laboratorio y concentración: del pedido o del catálogo en cache.
 * Nunca llama al API: los pedidos anteriores a este cambio no traían esos datos guardados.
 */
final class FarmadocOrderItems
{
    /**
     * @return list<array{product_id: int|null, name: string, sku: string|null, barcode: string|null, active_ingredient: string|null, laboratory: string|null, concentration: string|null, quantity: int}>
     */
    public static function forDisplay(FarmadocOrder $order): array
    {
        $lines = is_array($order->items) ? array_values(array_filter($order->items, 'is_array')) : [];

        if ($lines === []) {
            return [];
        }

        $accepted = self::acceptanceBarcodes($order);
        $catalog = null;
        $items = [];

        foreach ($lines as $line) {
            $productId = is_numeric($line['product_id'] ?? null) ? (int) $line['product_id'] : null;
            $name = self::text($line['name'] ?? null) ?? 'Medicamento sin nombre';
            $sku = self::text($line['sku'] ?? null);

            $barcode = self::text($line['barcode'] ?? null)
                ?? ($sku !== null ? ($accepted['sku'][$sku] ?? null) : null)
                ?? ($accepted['name'][self::key($name)] ?? null);

            $ingredient = self::text($line['active_ingredient'] ?? null);
            $laboratory = self::text($line['laboratory'] ?? null);
            $concentration = self::text($line['concentration'] ?? null);

            if ($productId !== null && ($barcode === null || $ingredient === null || $laboratory === null || $concentration === null)) {
                $catalog ??= self::catalogProducts();
                $fromCatalog = $catalog[$productId] ?? [];
                $barcode ??= self::text($fromCatalog['barcode'] ?? null);
                $ingredient ??= self::text($fromCatalog['active_ingredient'] ?? null);
                $laboratory ??= self::text($fromCatalog['brand'] ?? null);
                $concentration ??= self::text($fromCatalog['concentration'] ?? null);
                $sku ??= self::text($fromCatalog['sku'] ?? null);
            }

            $items[] = [
                'product_id' => $productId,
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'active_ingredient' => $ingredient,
                'laboratory' => $laboratory,
                'concentration' => $concentration,
                'quantity' => is_numeric($line['quantity'] ?? null) ? (int) $line['quantity'] : 0,
            ];
        }

        return $items;
    }

    /**
     * @return array{sku: array<string, string>, name: array<string, string>}
     */
    private static function acceptanceBarcodes(FarmadocOrder $order): array
    {
        $bySku = [];
        $byName = [];

        foreach (is_array($order->acceptance_items) ? $order->acceptance_items : [] as $item) {
            $barcode = is_array($item) ? self::text($item['barcode'] ?? null) : null;

            if ($barcode === null) {
                continue;
            }

            if (($sku = self::text($item['sku'] ?? null)) !== null) {
                $bySku[$sku] ??= $barcode;
            }

            if (($name = self::text($item['name'] ?? null)) !== null) {
                $byName[self::key($name)] ??= $barcode;
            }
        }

        return ['sku' => $bySku, 'name' => $byName];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function catalogProducts(): array
    {
        try {
            $cached = Cache::get(FarmadocMedicationOrder::CACHE_KEY) ?? Cache::get(FarmadocMedicationOrder::STALE_CACHE_KEY);
        } catch (Throwable) {
            return [];
        }

        $products = [];

        foreach (is_array($cached) && is_array($cached['medications'] ?? null) ? $cached['medications'] : [] as $medication) {
            if (is_array($medication) && is_numeric($medication['product_id'] ?? null)) {
                $products[(int) $medication['product_id']] = $medication;
            }
        }

        return $products;
    }

    private static function key(string $name): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
