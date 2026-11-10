<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventoryProduct;

/**
 * Precio final de referencia de un producto del inventario: costo + utilidad de su categoría.
 *
 * Es un cálculo de solo lectura para la tabla y el CSV de productos. No se persiste ni
 * alimenta órdenes, salidas ni documentos ya emitidos.
 */
final class OperationInventoryProductPricing
{
    public const MAX_PROFIT_PERCENTAGE = 1000;

    /**
     * Utilidad aplicada sobre el costo: precio = costo × (1 + % / 100).
     * Devuelve null si falta el costo o el porcentaje.
     */
    public static function finalPrice(mixed $cost, mixed $profitPercentage): ?float
    {
        if (! is_numeric($cost) || ! is_numeric($profitPercentage)) {
            return null;
        }

        return round((float) $cost * (1 + ((float) $profitPercentage / 100)), 2);
    }

    public static function profitPercentageFor(OperationInventoryProduct $product): ?float
    {
        $percentage = $product->category?->profit_percentage;

        return is_numeric($percentage) ? (float) $percentage : null;
    }

    public static function finalPriceFor(OperationInventoryProduct $product): ?float
    {
        return self::finalPrice($product->cost, self::profitPercentageFor($product));
    }

    /**
     * Ganancia por unidad en USD (precio final − costo). Null si no hay precio final.
     */
    public static function profitAmountFor(OperationInventoryProduct $product): ?float
    {
        $finalPrice = self::finalPriceFor($product);

        return $finalPrice === null ? null : round($finalPrice - (float) $product->cost, 2);
    }

    /**
     * Motivo por el que el producto no tiene precio final, en lenguaje del analista.
     */
    public static function missingPriceReason(OperationInventoryProduct $product): ?string
    {
        if (self::finalPriceFor($product) !== null) {
            return null;
        }

        if ($product->category === null) {
            return 'Asigne una categoría al producto para calcular su precio.';
        }

        if (! is_numeric($product->cost)) {
            return 'Cargue el costo del producto para calcular su precio.';
        }

        return 'Defina el porcentaje de utilidad en la categoría «'.$product->category->name.'».';
    }
}
