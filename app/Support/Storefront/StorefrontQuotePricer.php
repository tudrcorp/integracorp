<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Fee;
use Illuminate\Support\Collection;

/**
 * Precio comercial de una cotización en la PWA.
 *
 * Un plan con coberturas no se suma: el cliente elige una cobertura.
 * Por eso el total se expresa como «desde / hasta», no como un gran total
 * inflado.
 *
 * @phpstan-type PriceLine array{
 *     age_range_id: int,
 *     total_persons: int,
 *     desde: float,
 *     hasta: float
 * }
 * @phpstan-type PriceQuote array{
 *     desde: float,
 *     hasta: float,
 *     is_range: bool,
 *     persons: int,
 *     lines: list<PriceLine>
 * }
 */
final class StorefrontQuotePricer
{
    /**
     * @param  list<array{plan_id?: int, age_range_id: int, total_persons: int}>  $entries
     * @param  Collection<int, Fee>  $fees
     * @return PriceQuote
     */
    public static function quote(array $entries, Collection $fees): array
    {
        $lines = [];
        $desde = 0.0;
        $hasta = 0.0;
        $persons = 0;

        foreach ($entries as $entry) {
            $ageRangeId = (int) ($entry['age_range_id'] ?? 0);
            $totalPersons = max(0, (int) ($entry['total_persons'] ?? 0));

            if ($ageRangeId <= 0 || $totalPersons < 1) {
                continue;
            }

            $rangeFees = $fees
                ->filter(static fn (Fee $fee): bool => (int) $fee->age_range_id === $ageRangeId)
                ->map(static fn (Fee $fee): float => (float) $fee->price)
                ->filter(static fn (float $price): bool => $price >= 0)
                ->values();

            if ($rangeFees->isEmpty()) {
                continue;
            }

            $min = (float) $rangeFees->min();
            $max = (float) $rangeFees->max();
            $lineDesde = $min * $totalPersons;
            $lineHasta = $max * $totalPersons;

            $lines[] = [
                'age_range_id' => $ageRangeId,
                'total_persons' => $totalPersons,
                'desde' => $lineDesde,
                'hasta' => $lineHasta,
            ];

            $desde += $lineDesde;
            $hasta += $lineHasta;
            $persons += $totalPersons;
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'is_range' => $hasta > $desde + 0.009,
            'persons' => $persons,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array{plan_id?: int, age_range_id: int, total_persons: int}>  $entries
     * @return PriceQuote
     */
    public static function quoteForPlan(int $planId, array $entries): array
    {
        $fees = Fee::query()
            ->where('plan_id', $planId)
            ->whereNotNull('price')
            ->get();

        return self::quote($entries, $fees);
    }

    /**
     * @param  PriceQuote  $quote
     */
    public static function headline(array $quote): string
    {
        if ($quote['persons'] < 1 || $quote['desde'] <= 0) {
            return 'Completa los datos para ver el precio';
        }

        $desde = StorefrontPlanNarrative::formatMoney($quote['desde']);

        if ($quote['is_range']) {
            return 'Desde '.$desde.' al año';
        }

        return $desde.' al año';
    }

    /**
     * @param  PriceQuote  $quote
     */
    public static function amountLabel(array $quote): string
    {
        if ($quote['persons'] < 1 || $quote['desde'] <= 0) {
            return 'Completa los datos para ver el precio';
        }

        $desde = StorefrontPlanNarrative::formatMoney($quote['desde']);

        if ($quote['is_range']) {
            return 'Desde '.$desde;
        }

        return $desde;
    }

    /**
     * @param  PriceQuote  $quote
     */
    public static function coverageLabel(array $quote): string
    {
        $persons = (int) $quote['persons'];

        if ($persons < 1) {
            return '';
        }

        if ($persons === 1) {
            return 'Al año · 1 persona';
        }

        return 'Al año · '.$persons.' personas';
    }
}
