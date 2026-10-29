<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Models\AgeRange;

/**
 * Arma las filas de rango de edad de una cotización individual o corporativa.
 * El cotizador corporativo no debe vaciar el plan al rehidratar la categoría.
 */
final class QuoteAgeRangeSelection
{
    public const TYPE_BASICO = 'BASICO';

    public const TYPE_DRESS_TAILOR = 'DRESS-TAILOR';

    public static function normalizeType(mixed $type): string
    {
        return $type === self::TYPE_DRESS_TAILOR ? self::TYPE_DRESS_TAILOR : self::TYPE_BASICO;
    }

    public static function categoryChanged(mixed $currentType, mixed $nextType): bool
    {
        return self::normalizeType($currentType) !== self::normalizeType($nextType);
    }

    /**
     * @return list<array{plan_id: int, age_range_id: null, total_persons: null}>
     */
    public static function emptyRowsForPlan(int $planId): array
    {
        if ($planId <= 0) {
            return [];
        }

        $count = self::countForPlan($planId);

        if ($count === 0) {
            return [];
        }

        return array_map(
            static fn (): array => [
                'plan_id' => $planId,
                'age_range_id' => null,
                'total_persons' => null,
            ],
            range(0, $count - 1),
        );
    }

    /**
     * Cotizador individual: una fila por cada rango de edad del plan, ya elegido, para que el
     * analista solo indique cuántas personas hay en cada uno. Conserva las personas que ya
     * estuvieran indicadas en filas del mismo rango.
     *
     * @return list<array{plan_id: int, age_range_id: int, range_label: string, total_persons: int|null}>
     */
    public static function presetRowsForPlan(int $planId, mixed $current = []): array
    {
        if ($planId <= 0) {
            return [];
        }

        $persons = [];

        foreach (is_array($current) ? $current : [] as $row) {
            if (is_array($row) && is_numeric($row['age_range_id'] ?? null) && is_numeric($row['total_persons'] ?? null)) {
                $persons[(int) $row['age_range_id']] = (int) $row['total_persons'];
            }
        }

        $options = self::optionsForPlan($planId);

        return array_values(array_map(
            static fn (int $id, string $label): array => [
                'plan_id' => $planId,
                'age_range_id' => $id,
                'range_label' => $label,
                'total_persons' => $persons[$id] ?? null,
            ],
            array_keys($options),
            array_values($options),
        ));
    }

    /**
     * Filas preparadas, o null si las que hay ya son exactamente las del plan.
     *
     * @return list<array{plan_id: int, age_range_id: int, range_label: string, total_persons: int|null}>|null
     */
    public static function presetRowsIfMissing(mixed $plan, mixed $details): ?array
    {
        $planId = self::selectedPlanId($plan);

        if ($planId === null) {
            return [];
        }

        $expected = array_keys(self::optionsForPlan($planId));
        $current = array_values(array_map(
            static fn (mixed $row): int => is_array($row) && is_numeric($row['age_range_id'] ?? null) ? (int) $row['age_range_id'] : 0,
            is_array($details) ? $details : [],
        ));
        $labelsReady = is_array($details) && collect($details)->every(static fn (mixed $row): bool => is_array($row) && filled($row['range_label'] ?? null));

        return $current === $expected && $labelsReady ? null : self::presetRowsForPlan($planId, $details);
    }

    public static function countForPlan(int $planId): int
    {
        if ($planId <= 0) {
            return 0;
        }

        return AgeRange::query()
            ->where('plan_id', $planId)
            ->count();
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForPlan(int $planId): array
    {
        if ($planId <= 0) {
            return [];
        }

        return AgeRange::query()
            ->where('plan_id', $planId)
            ->orderBy('age_init')
            ->orderBy('id')
            ->pluck('range', 'id')
            ->all();
    }

    public static function selectedPlanId(mixed $plan): ?int
    {
        if ($plan === 'CM' || blank($plan) || ! is_numeric($plan)) {
            return null;
        }

        $planId = (int) $plan;

        return $planId > 0 ? $planId : null;
    }

    /**
     * @return list<array{plan_id: int, age_range_id: null, total_persons: null}>|null
     */
    public static function rowsIfMissing(mixed $plan, mixed $details): ?array
    {
        $planId = self::selectedPlanId($plan);

        if ($planId === null) {
            return [];
        }

        if (self::detailsHaveRows($details, $planId)) {
            return null;
        }

        return self::emptyRowsForPlan($planId);
    }

    public static function detailsHaveRows(mixed $details, int $planId): bool
    {
        if (! is_array($details) || $details === []) {
            return false;
        }

        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowPlanId = (int) ($row['plan_id'] ?? 0);

            if ($rowPlanId === $planId || $rowPlanId === 0) {
                return true;
            }
        }

        return false;
    }
}
