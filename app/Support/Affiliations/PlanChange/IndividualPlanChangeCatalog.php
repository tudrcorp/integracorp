<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PlanChange;

use App\Enums\PlanQuotableScope;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Qué planes, coberturas y rangos de edad puede elegir el analista al cambiar el plan de
 * una afiliación individual. Solo lo que tiene tarifa en el catálogo: un plan sin tarifa
 * dejaría a los afiliados sin monto que cobrar.
 */
final class IndividualPlanChangeCatalog
{
    /**
     * Planes activos con tarifas, sin los que solo se cotizan para corporativos.
     *
     * @return array<int, string>
     */
    public static function planOptions(): array
    {
        $withFees = Fee::query()->whereNotNull('plan_id')->where('status', 'ACTIVO')->distinct()->pluck('plan_id')->map(fn ($id): int => (int) $id)->all();

        return Plan::query()
            ->whereIn('id', $withFees)
            ->where('status', 'ACTIVO')
            ->orderBy('id')
            ->get(['id', 'description', 'is_quotable', 'quotable_in'])
            ->reject(fn (Plan $plan): bool => (bool) $plan->is_quotable && self::onlyCorporate($plan))
            ->mapWithKeys(fn (Plan $plan): array => [(int) $plan->id => Str::upper((string) $plan->description)])
            ->all();
    }

    public static function usesCoverages(int $planId): bool
    {
        return ! (new AffiliationAffiliateFeeCalculator)->planHasNoCoverages($planId);
    }

    /**
     * Coberturas del plan que tienen al menos una tarifa activa.
     *
     * @return array<int, string>
     */
    public static function coverageOptions(int $planId): array
    {
        if (! self::usesCoverages($planId)) {
            return [];
        }

        $coverageIds = Fee::query()->forPlan($planId)->where('status', 'ACTIVO')->whereNotNull('coverage_id')->distinct()->pluck('coverage_id');

        return Coverage::query()
            ->whereIn('id', $coverageIds)
            ->orderBy('price')
            ->get(['id', 'price'])
            ->mapWithKeys(fn (Coverage $coverage): array => [(int) $coverage->id => 'US$ '.number_format((float) $coverage->price, 0, ',', '.')])
            ->all();
    }

    /**
     * Tarifas del plan y cobertura elegidos, una por rango de edad.
     *
     * @return Collection<int, Fee>
     */
    public static function fees(int $planId, ?int $coverageId): Collection
    {
        $query = Fee::query()->with('ageRange')->forPlan($planId)->where('status', 'ACTIVO');

        if (self::usesCoverages($planId)) {
            $query->where('coverage_id', $coverageId);
        } else {
            $query->whereNull('coverage_id');
        }

        return $query->get()->sortBy(fn (Fee $fee): int => (int) ($fee->ageRange?->age_init ?? 0))->values();
    }

    /**
     * Rangos de edad con tarifa en ese plan y cobertura: «0 a 45 años · US$ 300».
     *
     * @return array<int, string>
     */
    public static function ageRangeOptions(int $planId, ?int $coverageId): array
    {
        return self::fees($planId, $coverageId)
            ->filter(fn (Fee $fee): bool => $fee->age_range_id !== null)
            ->mapWithKeys(fn (Fee $fee): array => [
                (int) $fee->age_range_id => self::rangeLabel($fee->ageRange, $fee).' · US$ '.number_format((float) $fee->price, 2, ',', '.'),
            ])
            ->all();
    }

    /**
     * Tarifa del rango más cercano a una edad que el plan no cubre: el de mayor edad si es mayor,
     * el de menor edad si es menor. Solo se usa con una excepción autorizada.
     */
    public static function nearestFeeForAge(int $planId, ?int $coverageId, int $age): ?Fee
    {
        return self::fees($planId, $coverageId)
            ->filter(fn (Fee $fee): bool => $fee->ageRange !== null && filled($fee->ageRange->age_init) && filled($fee->ageRange->age_end))
            ->sortBy(fn (Fee $fee): int => match (true) {
                $age < (int) $fee->ageRange->age_init => (int) $fee->ageRange->age_init - $age,
                $age > (int) $fee->ageRange->age_end => $age - (int) $fee->ageRange->age_end,
                default => 0,
            })
            ->first();
    }

    public static function feeForAgeRange(int $planId, ?int $coverageId, int $ageRangeId): ?Fee
    {
        return self::fees($planId, $coverageId)->first(fn (Fee $fee): bool => (int) $fee->age_range_id === $ageRangeId);
    }

    public static function rangeLabel(?AgeRange $range, ?Fee $fee = null): string
    {
        $label = trim((string) ($range?->range ?? $fee?->range ?? ''));

        if ($label === '' && $range !== null && filled($range->age_init) && filled($range->age_end)) {
            $label = $range->age_init.' a '.$range->age_end;
        }

        return $label !== '' ? Str::of($label)->replace('años', '')->trim()->append(' años')->toString() : 'Sin rango';
    }

    private static function onlyCorporate(Plan $plan): bool
    {
        $scope = $plan->quotable_in;
        $value = $scope instanceof PlanQuotableScope ? $scope->value : (string) $scope;

        return strtolower($value) === 'corporate';
    }
}
