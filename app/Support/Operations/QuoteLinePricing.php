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
 *
 * Cantidad (27/11/2026): solo los medicamentos la llevan y la carga el analista. El precio del
 * proveedor sigue siendo unitario; costo y venta de la línea = unitario × cantidad, redondeados
 * por línea. Los demás ítems valen siempre 1, y un medicamento de una cotización anterior (sin
 * la clave `quantity`) también vale 1, así sus montos no cambian.
 */
final class QuoteLinePricing
{
    public const MAX_PERCENTAGE = ClinicalServiceCatalog::MAX_PROFIT_PERCENTAGE;

    /** Marca de las cotizaciones con % por ítem (las anteriores aplicaban un % global). */
    public const ITEM_PRICING_FLAG = 'per_item_profit';

    public const QUANTITY_KEY = 'quantity';

    public const MAX_QUANTITY = MedicationInvoiceIssuer::MAX_QUANTITY_PER_LINE;

    /**
     * Solo los medicamentos se cotizan por cantidad.
     */
    public static function quantityApplies(mixed $key): bool
    {
        return ClinicalServiceCatalog::kindFromKey((string) $key) === MedicationInvoiceWizard::KIND_MEDICATION;
    }

    /**
     * Cantidad escrita por el analista: entero entre 1 y {@see MAX_QUANTITY}, o null si no es válida.
     */
    public static function parseQuantity(mixed $value): ?int
    {
        if (is_int($value)) {
            $quantity = $value;
        } elseif (is_string($value) && preg_match('/^\s*\d+\s*$/', $value) === 1) {
            $quantity = (int) trim($value);
        } elseif (is_float($value) && floor($value) === $value) {
            $quantity = (int) $value;
        } else {
            return null;
        }

        return ($quantity >= 1 && $quantity <= self::MAX_QUANTITY) ? $quantity : null;
    }

    /**
     * Cantidad efectiva de una línea: 1 si el ítem no es un medicamento o si es una cotización
     * anterior sin la clave; null si es un medicamento con la cantidad vacía o no válida.
     *
     * @param  array<string, mixed>  $line
     */
    public static function quantity(array $line): ?int
    {
        if (! self::quantityApplies($line['key'] ?? null) || ! array_key_exists(self::QUANTITY_KEY, $line)) {
            return 1;
        }

        return self::parseQuantity($line[self::QUANTITY_KEY]);
    }

    /**
     * Valor que se guarda en la cotización: la cantidad del analista en medicamentos, 1 en lo demás.
     *
     * @param  array<string, mixed>  $line
     */
    public static function storedQuantity(array $line): ?int
    {
        if (! self::quantityApplies($line['key'] ?? null)) {
            return 1;
        }

        return self::parseQuantity($line[self::QUANTITY_KEY] ?? null);
    }

    /**
     * Costo de la línea (precio unitario del proveedor × cantidad). Null si falta precio o cantidad.
     *
     * @param  array<string, mixed>  $line
     */
    public static function lineCost(array $line): ?float
    {
        $unitCost = is_numeric($line['unit_price_usd'] ?? null) ? round((float) $line['unit_price_usd'], 2) : null;
        $quantity = self::quantity($line);

        return ($unitCost === null || $quantity === null) ? null : round($unitCost * $quantity, 2);
    }

    /**
     * Precio de venta de la línea (precio de venta unitario × cantidad). Null si falta precio o cantidad.
     *
     * @param  array<string, mixed>  $line
     */
    public static function lineSalePrice(array $line): ?float
    {
        $unitCost = is_numeric($line['unit_price_usd'] ?? null) ? round((float) $line['unit_price_usd'], 2) : null;
        $unitPrice = self::salePrice($unitCost, $line['profit_percentage'] ?? null);
        $quantity = self::quantity($line);

        return ($unitPrice === null || $quantity === null) ? null : round($unitPrice * $quantity, 2);
    }

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
     * `cost`, `profit` y `price` de cada fila son los de la línea completa (unitario × cantidad).
     *
     * @param  array<int|string, mixed>  $lines  filas con unit_price_usd (costo unitario), profit_percentage y quantity
     * @return array{
     *     lines: list<array{key: string, label: string, quantity: int|null, unit_cost: float|null, unit_price: float|null, cost: float|null, percentage: float|null, profit: float|null, price: float|null}>,
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

            $unitCost = is_numeric($line['unit_price_usd'] ?? null) ? round((float) $line['unit_price_usd'], 2) : null;
            $percentage = self::percentage($line['profit_percentage'] ?? null);
            $unitPrice = self::salePrice($unitCost, $percentage);
            $quantity = self::quantity($line);
            $lineCost = self::lineCost($line);
            $price = self::lineSalePrice($line);

            if ($lineCost !== null && $price !== null) {
                $priced++;
                $cost += $lineCost;
                $total += $price;
            }

            $rows[] = [
                'key' => (string) ($line['key'] ?? ''),
                'label' => (string) ($line['label'] ?? '—'),
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'unit_price' => $unitPrice,
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
     * Mensaje si algún % de utilidad no es válido (vacío se acepta: sin utilidad) o si a un
     * medicamento le falta la cantidad.
     *
     * @param  array<int|string, mixed>  $lines
     */
    public static function validationMessage(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            if (self::quantity($line) === null) {
                return 'Indique la cantidad de «'.($line['label'] ?? 'un medicamento').'»: un número entero entre 1 y '.self::MAX_QUANTITY.'.';
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
