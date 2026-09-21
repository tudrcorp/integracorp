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
     * Estados que cuentan como población al recalcular totales.
     *
     * Es el comportamiento histórico y se mantiene como valor por defecto para no
     * alterar los flujos que ya activan al afiliado antes de recalcular.
     *
     * @var list<string>
     */
    public const DEFAULT_STATUSES = ['ACTIVO'];

    /**
     * Recalcula total_persons y subtotales de cada fila de plan según los afiliados que coinciden
     * en plan, cobertura y edad dentro del rango de la fila.
     *
     * @param  list<string>|null  $statuses  Estados a contabilizar; por defecto solo ACTIVO.
     */
    public static function syncPlanRowTotalsFromAffiliates(AffiliationCorporate $owner, ?array $statuses = null): void
    {
        $statuses = self::normalizeStatuses($statuses);

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
                ->whereIn('status', $statuses)
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
     * Sincroniza poblation, fee_anual y total_amount de la afiliación con la suma de sus afiliados.
     *
     * @param  list<string>|null  $statuses  Estados a contabilizar; por defecto solo ACTIVO.
     */
    public static function syncOwnerTotalsFromAffiliates(AffiliationCorporate $owner, ?array $statuses = null): void
    {
        $statuses = self::normalizeStatuses($statuses);

        $sumFee = (float) AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', $statuses)
            ->sum('fee');

        $count = (int) AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', $statuses)
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
     * @param  list<string>|null  $statuses
     * @return list<string>
     */
    private static function normalizeStatuses(?array $statuses): array
    {
        $statuses = array_values(array_filter(
            array_map(static fn (mixed $status): string => trim((string) $status), $statuses ?? []),
            static fn (string $status): bool => $status !== '',
        ));

        return $statuses === [] ? self::DEFAULT_STATUSES : $statuses;
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
