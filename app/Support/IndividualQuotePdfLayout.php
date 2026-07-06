<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Fee;
use App\Models\Plan;

class IndividualQuotePdfLayout
{
    public const Inicial = 'inicial';

    public const Ideal = 'ideal';

    public const Especial = 'especial';

    /**
     * Resuelve la plantilla PDF para un plan (legacy 1/2/3 o planes nuevos por estructura).
     */
    public static function resolve(int $planId): string
    {
        return match ($planId) {
            1 => self::Inicial,
            3 => self::Especial,
            2 => self::Ideal,
            default => self::resolveFromStructure($planId),
        };
    }

    /**
     * Indica si el detalle debe incluir join de coberturas (plan ideal/especial).
     */
    public static function usesCoverageBreakdown(string $layout): bool
    {
        return in_array($layout, [self::Ideal, self::Especial], true);
    }

    private static function resolveFromStructure(int $planId): string
    {
        $plan = Plan::query()->find($planId);

        if ($plan === null) {
            return self::Ideal;
        }

        $feesPerAgeRange = Fee::query()
            ->whereIn('age_range_id', function ($query) use ($planId): void {
                $query->select('id')
                    ->from('age_ranges')
                    ->where('plan_id', $planId);
            })
            ->selectRaw('age_range_id, COUNT(*) as fee_count')
            ->groupBy('age_range_id')
            ->pluck('fee_count');

        if ($feesPerAgeRange->isEmpty()) {
            return self::Ideal;
        }

        $maxFees = (int) $feesPerAgeRange->max();
        $ageRangeCount = $feesPerAgeRange->count();

        if ($ageRangeCount === 1 && $maxFees === 1) {
            return self::Inicial;
        }

        return self::Ideal;
    }
}
