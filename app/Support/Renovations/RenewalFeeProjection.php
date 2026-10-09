<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;

/**
 * Tarifa que la propuesta de renovación (`renovations`) muestra para cada
 * afiliado: la misma que se aplicaría al aceptarla hoy.
 *
 * - Dentro del período de renovación la aceptación es normal y cobra con la
 *   edad del día.
 * - Fuera del período solo se acepta como renovación anticipada, que cobra con
 *   la edad que el afiliado tendrá en la fecha de renovación.
 * - Sin tarifa resoluble la aceptación no toca al afiliado: conserva la vigente.
 */
final class RenewalFeeProjection
{
    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $calculator,
    ) {}

    public static function referenceDate(bool $isInRenewalPeriod, Carbon $today, Carbon $renewalDate): Carbon
    {
        return $isInRenewalPeriod
            ? $today->copy()->startOfDay()
            : $renewalDate->copy()->startOfDay();
    }

    public function canRecalculateFees(Affiliation $affiliation): bool
    {
        return $this->calculator->isInitialPlanWithoutCoverage($affiliation)
            || filled($affiliation->coverage_id);
    }

    /**
     * @return array{annual_fee: float, age_range_id: int|null, priced: bool}
     */
    public function forAffiliate(Affiliation $affiliationForFees, Affiliate $affiliate, Carbon $referenceDate): array
    {
        $amounts = $this->canRecalculateFees($affiliationForFees)
            ? $this->calculator->calculateAffiliateAmountsForRenewal($affiliationForFees, $affiliate, $referenceDate)
            : null;

        if ($amounts !== null) {
            return [
                'annual_fee' => (float) $amounts['annual_fee'],
                'age_range_id' => $amounts['age_range_id'],
                'priced' => true,
            ];
        }

        return [
            'annual_fee' => (float) $affiliate->fee,
            'age_range_id' => $affiliate->age_range_id !== null ? (int) $affiliate->age_range_id : null,
            'priced' => false,
        ];
    }
}
