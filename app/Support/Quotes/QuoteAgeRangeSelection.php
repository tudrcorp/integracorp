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
