<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use Illuminate\Database\Eloquent\Builder;

final class CorporateAffiliatePlanSyncService
{
    /**
     * Recalcula total_persons y subtotales de cada fila de plan según afiliados ACTIVOS que coinciden
     * en plan, cobertura y edad dentro del rango de la fila.
     */
    public static function syncPlanRowTotalsFromAffiliates(AffiliationCorporate $owner): void
    {
        $rows = AfilliationCorporatePlan::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->with('ageRange')
            ->get();

        foreach ($rows as $row) {
            $ageRange = $row->ageRange;
            if (! $ageRange instanceof AgeRange) {
                continue;
            }

            $countQuery = AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->where('plan_id', $row->plan_id)
                ->where('status', 'ACTIVO')
                ->whereNotNull('age')
                ->whereBetween('age', [(int) $ageRange->age_init, (int) $ageRange->age_end]);

            self::applyAffiliateCoverageScope($countQuery, $row->coverage_id);

            $count = $countQuery->count();

            $row->total_persons = $count;
            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
            $row->save();
        }
    }

    /**
     * Sincroniza poblation, fee_anual y total_amount de la afiliación con la suma de afiliados activos.
     */
    public static function syncOwnerTotalsFromAffiliates(AffiliationCorporate $owner): void
    {
        $sumFee = (float) AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->where('status', 'ACTIVO')
            ->sum('fee');

        $count = (int) AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->where('status', 'ACTIVO')
            ->count();

        $owner->fee_anual = $sumFee;
        $owner->poblation = $count;
        $owner->total_amount = CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(
            $sumFee,
            $owner->payment_frequency
        );
        $owner->save();
    }

    /**
     * @param  Builder<AffiliateCorporate>  $query
     */
    private static function applyAffiliateCoverageScope(Builder $query, mixed $coverageId): void
    {
        if ($coverageId === null || (int) $coverageId === 0) {
            $query->where(function ($q): void {
                $q->whereNull('coverage_id')->orWhere('coverage_id', 0);
            });

            return;
        }

        $query->where('coverage_id', $coverageId);
    }
}
