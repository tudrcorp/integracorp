<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssociateAffiliatesWithCorporatePlanService
{
    /**
     * Asocia afiliados seleccionados al plan/cobertura/tarifa indicados y actualiza la fila de plan corporativo.
     * Tras la operación sincroniza todas las filas de plan y los totales del titular.
     *
     * @param  list<int|string>  $affiliateIds
     * @param  array{plan_id: int|string, age_range_id: int|string, fee: float|int|string, coverage_id?: int|string|null}  $planData
     */
    public static function run(
        AffiliationCorporate $owner,
        AfilliationCorporatePlan $planRow,
        array $affiliateIds,
        array $planData,
    ): void {
        $planId = (int) $planData['plan_id'];
        $ageRangeId = (int) $planData['age_range_id'];
        $coverageId = self::normalizeOptionalCoverageId($planData['coverage_id'] ?? null);
        $fee = (float) $planData['fee'];

        if ($planRow->affiliation_corporate_id !== $owner->id) {
            throw ValidationException::withMessages([
                'plan' => ['La fila de plan no pertenece a esta afiliación.'],
            ]);
        }

        $allowedPlanIds = $owner->affiliationCorporatePlans()->pluck('plan_id')->unique()->all();
        if (! in_array($planId, $allowedPlanIds, true)) {
            throw ValidationException::withMessages([
                'plan_id' => ['El plan debe estar asociado a esta afiliación corporativa.'],
            ]);
        }

        $ageRange = AgeRange::query()->whereKey($ageRangeId)->where('plan_id', $planId)->first();
        if (! $ageRange instanceof AgeRange) {
            throw ValidationException::withMessages([
                'age_range_id' => ['Rango de edad inválido para el plan seleccionado.'],
            ]);
        }

        if (! self::feeMatchesAgeRangeAndCoverage($ageRangeId, $coverageId, $fee)) {
            throw ValidationException::withMessages([
                'fee' => ['La tarifa no coincide con el rango de edad'.($coverageId !== null ? ' y la cobertura' : '').' del plan.'],
            ]);
        }

        $affiliateIds = array_values(array_unique(array_map('intval', $affiliateIds)));
        if ($affiliateIds === []) {
            throw ValidationException::withMessages([
                'affiliate_ids' => ['Seleccione al menos un afiliado.'],
            ]);
        }

        $affiliates = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->id)
            ->whereIn('id', $affiliateIds)
            ->get();

        if ($affiliates->count() !== count($affiliateIds)) {
            throw ValidationException::withMessages([
                'affiliate_ids' => ['Uno o más afiliados no pertenecen a esta afiliación.'],
            ]);
        }

        self::assertAffiliatesWithinPlanAgeRange($affiliates, $ageRange);

        $frequency = (string) $owner->payment_frequency;

        DB::transaction(function () use ($owner, $planRow, $affiliates, $planId, $ageRangeId, $coverageId, $fee, $frequency): void {
            foreach ($affiliates as $affiliate) {
                $perPeriod = CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $frequency);
                $affiliate->update([
                    'plan_id' => $planId,
                    'coverage_id' => $coverageId,
                    'fee' => $fee,
                    'payment_frequency' => $frequency,
                    'subtotal_anual' => $fee,
                    'subtotal_payment_frequency' => $perPeriod,
                    'subtotal_daily' => $fee / 30,
                    'status' => 'ACTIVO',
                ]);
            }

            $planRow->update([
                'plan_id' => $planId,
                'coverage_id' => $coverageId,
                'age_range_id' => $ageRangeId,
                'fee' => $fee,
                'payment_frequency' => $frequency,
                'code_affiliation' => $owner->code,
            ]);

            CorporateAffiliatePlanSyncService::syncPlanRowTotalsFromAffiliates($owner);
            CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($owner);
        });
    }

    /**
     * IDs de afiliados de la corporación cuya edad cae dentro del rango de edad de la fila de plan (excluye sin edad).
     *
     * @return list<int>
     */
    public static function idsForAffiliatesMatchingPlanRowAgeRange(
        AffiliationCorporate $owner,
        AfilliationCorporatePlan $planRow,
    ): array {
        $planRow->loadMissing('ageRange');
        $ageRange = $planRow->ageRange;
        if (! $ageRange instanceof AgeRange) {
            return [];
        }

        $ageInit = (int) $ageRange->age_init;
        $ageEnd = (int) $ageRange->age_end;

        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->id)
            ->whereNotNull('age')
            ->whereBetween('age', [$ageInit, $ageEnd])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     */
    private static function assertAffiliatesWithinPlanAgeRange(
        Collection $affiliates,
        AgeRange $ageRange,
    ): void {
        $ageInit = (int) $ageRange->age_init;
        $ageEnd = (int) $ageRange->age_end;
        $rangeLabel = $ageRange->range !== null && $ageRange->range !== ''
            ? $ageRange->range.' años'
            : $ageInit.'–'.$ageEnd.' años';

        $missingAge = [];
        $outOfRange = [];

        foreach ($affiliates as $affiliate) {
            $label = trim(($affiliate->first_name ?? '').' '.($affiliate->last_name ?? ''));
            if ($label === '') {
                $label = 'Afiliado #'.$affiliate->getKey();
            }

            if ($affiliate->age === null || $affiliate->age === '') {
                $missingAge[] = $label;

                continue;
            }

            $age = (int) $affiliate->age;
            if ($age < $ageInit || $age > $ageEnd) {
                $outOfRange[] = $label.' (edad '.$age.')';
            }
        }

        $parts = [];
        if ($missingAge !== []) {
            $parts[] = 'Sin edad registrada (registre la edad para poder asociar el plan): '.implode(', ', $missingAge).'.';
        }
        if ($outOfRange !== []) {
            $parts[] = 'Fuera del rango de edad del plan ('.$rangeLabel.'): '.implode(', ', $outOfRange).'.';
        }

        if ($parts !== []) {
            throw ValidationException::withMessages([
                'age_range' => [implode(' ', $parts)],
            ]);
        }
    }

    private static function normalizeOptionalCoverageId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int === 0 ? null : $int;
    }

    /**
     * Planes iniciales / tarifa plana: sin cobertura en BD (null o 0). Se valida la tarifa contra fees del rango de edad.
     */
    private static function feeMatchesAgeRangeAndCoverage(int $ageRangeId, ?int $coverageId, float $fee): bool
    {
        $matchesPrice = static fn (Fee $f): bool => abs((float) $f->price - $fee) < 0.01;

        if ($coverageId !== null) {
            return Fee::query()
                ->where('age_range_id', $ageRangeId)
                ->where('coverage_id', $coverageId)
                ->get()
                ->contains($matchesPrice);
        }

        $feesWithoutCoverage = Fee::query()
            ->where('age_range_id', $ageRangeId)
            ->where(function ($query): void {
                $query->whereNull('coverage_id')->orWhere('coverage_id', 0);
            })
            ->get();

        if ($feesWithoutCoverage->contains($matchesPrice)) {
            return true;
        }

        return Fee::query()
            ->where('age_range_id', $ageRangeId)
            ->get()
            ->contains($matchesPrice);
    }
}
