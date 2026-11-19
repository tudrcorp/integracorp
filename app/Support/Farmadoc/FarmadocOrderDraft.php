<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

/**
 * Arma las líneas que acepta POST /api/external/orders.
 * El analista no ve precios: cada línea sale con unit_price 0.
 */
final class FarmadocOrderDraft
{
    public const MAX_LINES = 40;

    public const MAX_QUANTITY = 9999;

    /**
     * @param  list<array{product_id?: mixed, quantity?: mixed}>  $lines
     * @param  list<array<string, mixed>>  $catalog
     * @return list<array{product_id: int, quantity: int, unit_price: float, product_name_snapshot: string, sku_snapshot: string|null}>
     */
    public static function items(array $lines, array $catalog): array
    {
        if ($lines === []) {
            throw new FarmadocException('Agregue al menos un medicamento al pedido.');
        }

        $byProduct = [];

        foreach ($catalog as $medication) {
            if (! is_array($medication) || ! isset($medication['product_id'])) {
                continue;
            }

            $byProduct[(int) $medication['product_id']] = $medication;
        }

        $merged = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($productId < 1 || ! isset($byProduct[$productId])) {
                throw new FarmadocException('Hay un medicamento que ya no está en el catálogo de Farmadoc. Actualice el listado.');
            }

            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                $name = (string) ($byProduct[$productId]['name'] ?? 'el medicamento');

                throw new FarmadocException('Indique una cantidad entre 1 y '.self::MAX_QUANTITY.' para '.$name.'.');
            }

            $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;

            if ($merged[$productId] > self::MAX_QUANTITY) {
                $name = (string) ($byProduct[$productId]['name'] ?? 'el medicamento');

                throw new FarmadocException('La cantidad de '.$name.' no puede superar '.self::MAX_QUANTITY.'.');
            }
        }

        if (count($merged) > self::MAX_LINES) {
            throw new FarmadocException('Un pedido admite hasta '.self::MAX_LINES.' medicamentos. Envíe este y arme otro.');
        }

        $items = [];

        foreach ($merged as $productId => $quantity) {
            $medication = $byProduct[$productId];
            $sku = $medication['sku'] ?? null;

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => 0.0,
                'product_name_snapshot' => (string) $medication['name'],
                'sku_snapshot' => is_string($sku) && $sku !== '' ? $sku : null,
            ];
        }

        return $items;
    }
}
