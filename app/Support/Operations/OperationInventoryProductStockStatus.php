<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventorySetting;

/**
 * Estado de existencia de un producto del inventario según el umbral de stock bajo
 * de Parámetros de Inventario. Misma regla que el encabezado del listado de productos
 * (stock bajo = existencia total entre 1 y el umbral).
 */
final class OperationInventoryProductStockStatus
{
    public const OUT_OF_STOCK = 'out_of_stock';

    public const LOW_STOCK = 'low_stock';

    public const AVAILABLE = 'available';

    /**
     * Umbral vigente, una sola lectura por petición.
     */
    public static function threshold(): int
    {
        return once(static fn (): int => OperationInventorySetting::current()->lowStockThreshold());
    }

    public static function for(int $totalExistence, ?int $threshold = null): string
    {
        $threshold ??= self::threshold();

        if ($totalExistence <= 0) {
            return self::OUT_OF_STOCK;
        }

        return $totalExistence <= $threshold ? self::LOW_STOCK : self::AVAILABLE;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::OUT_OF_STOCK => 'Sin existencia',
            self::LOW_STOCK => 'Stock bajo',
            default => 'Disponible',
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            self::OUT_OF_STOCK => 'danger',
            self::LOW_STOCK => 'warning',
            default => 'success',
        };
    }

    public static function icon(string $status): string
    {
        return match ($status) {
            self::OUT_OF_STOCK => 'heroicon-m-no-symbol',
            self::LOW_STOCK => 'heroicon-m-exclamation-triangle',
            default => 'heroicon-m-check-circle',
        };
    }
}
