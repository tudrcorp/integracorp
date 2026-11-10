<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventoryProductCategory;

/**
 * Precio de venta de cada ítem de una cotización de lo NO CUBIERTO (10/11/2026).
 *
 * Cada ítem lleva su propio % de utilidad: precio = costo × (1 + % / 100). Un % vacío significa
 * «sin utilidad» y el ítem se cobra al costo. El total de la cotización es la suma de los precios
 * de venta redondeados por línea, así la factura y el PDF cuadran centavo a centavo.
 *
 * `porcentaje_ganancia` de la cotización sigue existiendo, pero ahora guarda el % efectivo
 * (ganancia total / costo total), para las pantallas e informes que ya lo leían.
 */
final class QuoteLinePricing
{
    public const MAX_PERCENTAGE = ClinicalServiceCatalog::MAX_PROFIT_PERCENTAGE;

    /** Marca de las cotizaciones con % por ítem (las anteriores aplicaban un % global). */
    public const ITEM_PRICING_FLAG = 'per_item_profit';

    public static function percentage(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $percentage = round((float) $value, 2);

        return $percentage < 0 ? null : $percentage;
    }

    public static function salePrice(mixed $cost, mixed $percentage): ?float
    {
        if ($cost === null || $cost === '' || ! is_numeric($cost)) {
            return null;
        }

        $cost = (float) $cost;
        $percentage = self::percentage($percentage) ?? 0.0;

        return round($cost + ($cost * $percentage / 100), 2);
    }

    /**
     * @param  array<int|string, mixed>  $lines  filas con unit_price_usd (costo) y profit_percentage
     * @return array{
     *     lines: list<array{key: string, label: string, cost: float|null, percentage: float|null, profit: float|null, price: float|null}>,
     *     cost: float|null,
     *     profit: float|null,
     *     total: float|null,
     *     effective_percentage: float|null,
     *     priced: int,
     *     count: int
     * }
     */
    public static function totals(array $lines): array
    {
        $rows = [];
        $cost = 0.0;
        $total = 0.0;
        $priced = 0;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lineCost = is_numeric($line['unit_price_usd'] ?? null) ? round((float) $line['unit_price_usd'], 2) : null;
            $percentage = self::percentage($line['profit_percentage'] ?? null);
            $price = self::salePrice($lineCost, $percentage);

            if ($lineCost !== null && $price !== null) {
                $priced++;
                $cost += $lineCost;
                $total += $price;
            }

            $rows[] = [
                'key' => (string) ($line['key'] ?? ''),
                'label' => (string) ($line['label'] ?? '—'),
                'cost' => $lineCost,
                'percentage' => $percentage,
                'profit' => ($lineCost !== null && $price !== null) ? round($price - $lineCost, 2) : null,
                'price' => $price,
            ];
        }

        if ($priced === 0) {
            return ['lines' => $rows, 'cost' => null, 'profit' => null, 'total' => null, 'effective_percentage' => null, 'priced' => 0, 'count' => count($rows)];
        }

        $cost = round($cost, 2);
        $total = round($total, 2);
        $profit = round($total - $cost, 2);

        return [
            'lines' => $rows,
            'cost' => $cost,
            'profit' => $profit,
            'total' => $total,
            'effective_percentage' => self::effectivePercentage($cost, $profit),
            'priced' => $priced,
            'count' => count($rows),
        ];
    }

    public static function effectivePercentage(?float $cost, ?float $profit): ?float
    {
        if ($cost === null || $profit === null || $cost <= 0) {
            return null;
        }

        return round($profit * 100 / $cost, 2);
    }

    /**
     * % de utilidad sugerido para un ítem: el de su fila del catálogo (laboratorios, estudios,
     * especialistas) o el de la categoría «MEDICAMENTOS» del inventario. Null si no existe.
     */
    public static function defaultPercentage(string $key, ?string $label): ?float
    {
        $kind = ClinicalServiceCatalog::kindFromKey($key);

        if ($kind === MedicationInvoiceWizard::KIND_MEDICATION) {
            return self::medicationCategoryPercentage();
        }

        if (! ClinicalServiceCatalog::isServiceKind($kind)) {
            return null;
        }

        $entry = ClinicalServiceCatalog::find((string) $kind, ClinicalServiceCatalog::matchId((string) $kind, $label));

        return $entry !== null ? self::percentage($entry->getAttribute('profit_percentage')) : null;
    }

    public static function medicationCategoryPercentage(): ?float
    {
        return once(static function (): ?float {
            $categoryId = MedicationInventoryCatalog::medicationCategoryId();

            if ($categoryId === null) {
                return null;
            }

            return self::percentage(OperationInventoryProductCategory::query()->whereKey($categoryId)->value('profit_percentage'));
        });
    }

    /**
     * Mensaje si algún % de utilidad no es válido (vacío se acepta: sin utilidad).
     *
     * @param  array<int|string, mixed>  $lines
     */
    public static function validationMessage(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $raw = $line['profit_percentage'] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > self::MAX_PERCENTAGE) {
                return 'El % de utilidad de «'.($line['label'] ?? 'un ítem').'» debe estar entre 0 y '.self::MAX_PERCENTAGE.', o quedar vacío.';
            }
        }

        return null;
    }

    public static function usesItemPricing(mixed $items): bool
    {
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && (bool) ($item[self::ITEM_PRICING_FLAG] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
