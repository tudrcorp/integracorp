<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Coverage;
use Illuminate\Support\Collection;

class QuotePdfCoverageTable
{
    /**
     * @param  iterable<string|int, mixed>  $groupedByAgeRange
     * @return array{
     *     coverageColumns: list<float>,
     *     coverageCount: int,
     *     rows: list<array{age_range: string, total_persons: int, amounts: array<string, float>}>,
     *     totals: array<string, float|null>
     * }
     */
    public static function build(iterable $groupedByAgeRange, ?int $planId = null): array
    {
        if ($planId === null) {
            $planId = self::resolvePlanIdFromGroupedData($groupedByAgeRange);
        }

        $rows = [];
        $coveragesFromDetails = [];

        foreach ($groupedByAgeRange as $ageRange => $items) {
            $items = $items instanceof Collection ? $items : collect($items);
            $first = $items->first();

            if ($first === null) {
                continue;
            }

            $firstObject = self::asObject($first);
            $amounts = [];

            foreach ($items as $item) {
                $itemObject = self::asObject($item);
                $coveragePrice = self::resolveCoveragePrice($itemObject);

                if ($coveragePrice === null) {
                    continue;
                }

                $key = self::coverageKey($coveragePrice);
                $coveragesFromDetails[$key] = $coveragePrice;
                $amounts[$key] = round((float) ($itemObject->subtotal_anual ?? 0));
            }

            $rows[] = [
                'age_range' => (string) $ageRange,
                'total_persons' => (int) ($firstObject->total_persons ?? 0),
                'amounts' => $amounts,
            ];
        }

        $coverageColumns = self::resolveCoverageColumns($planId, $coveragesFromDetails);

        $totals = [];

        foreach ($coverageColumns as $price) {
            $key = self::coverageKey($price);
            $sum = 0;
            $hasValue = false;

            foreach ($rows as $row) {
                if (array_key_exists($key, $row['amounts'])) {
                    $sum += $row['amounts'][$key];
                    $hasValue = true;
                }
            }

            $totals[$key] = $hasValue ? $sum : null;
        }

        return [
            'coverageColumns' => $coverageColumns,
            'coverageCount' => count($coverageColumns),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<string, float>  $coveragesFromDetails
     * @return list<float>
     */
    public static function resolveCoverageColumns(?int $planId, array $coveragesFromDetails): array
    {
        $columns = [];

        if ($planId !== null) {
            $columns = Coverage::query()
                ->where('plan_id', $planId)
                ->orderBy('price')
                ->pluck('price')
                ->map(fn ($price): float => (float) $price)
                ->values()
                ->all();
        }

        foreach ($coveragesFromDetails as $price) {
            $key = self::coverageKey($price);

            $exists = collect($columns)->contains(
                fn (float $columnPrice): bool => self::coverageKey($columnPrice) === $key,
            );

            if (! $exists) {
                $columns[] = $price;
            }
        }

        sort($columns, SORT_NUMERIC);

        return array_values($columns);
    }

    public static function resolvePlanIdFromGroupedData(mixed $data): ?int
    {
        $items = $data instanceof Collection ? $data : collect($data);
        $first = $items->flatten(1)->first();

        if ($first === null) {
            return null;
        }

        $object = self::asObject($first);

        if (! isset($object->plan_id)) {
            return null;
        }

        return (int) $object->plan_id;
    }

    public static function coverageKey(float $price): string
    {
        return (string) (int) round($price);
    }

    public static function formatLabel(float $price): string
    {
        $thousands = (int) round($price / 1000);

        if ($thousands >= 1 && fmod($price, 1000) === 0.0) {
            return $thousands.'K';
        }

        return number_format($price, 0, '.', ',');
    }

    private static function asObject(mixed $item): object
    {
        return is_object($item) ? $item : (object) $item;
    }

    private static function resolveCoveragePrice(object $item): ?float
    {
        if (isset($item->coverage) && is_numeric($item->coverage)) {
            return (float) $item->coverage;
        }

        return null;
    }
}
