<?php

declare(strict_types=1);

namespace App\Support\Filament\Renovations;

use App\Jobs\PrepareAffiliationRenovations;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Fee;
use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;

final class RenovationManualAcceptancePricing
{
    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     annual_fee: float,
     *     period_amount: float,
     *     age_range_id: int,
     *     coverage_id: int|null
     * }|null
     */
    public function amountsForTitularAgeRange(
        int $planId,
        ?int $coverageId,
        int $ageRangeId,
        string $paymentFrequency,
    ): ?array {
        $fee = $this->resolveFee($planId, $coverageId, $ageRangeId);

        if ($fee === null) {
            return null;
        }

        $annualFee = (float) $fee->price;

        return [
            'annual_fee' => $annualFee,
            'period_amount' => $this->calculator->totalAmountForPaymentFrequency($annualFee, $paymentFrequency),
            'age_range_id' => $ageRangeId,
            'coverage_id' => $planId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID
                ? null
                : $coverageId,
        ];
    }

    /**
     * @return array{
     *     subtotal_anual: float,
     *     subtotal_quarterly: float,
     *     subtotal_biannual: float,
     *     subtotal_monthly: float,
     *     titular_annual: float,
     *     total_persons: int
     * }|null
     */
    public function previewFamilyTotals(
        Affiliation $affiliation,
        int $planId,
        ?int $coverageId,
        int $titularAgeRangeId,
        string $paymentFrequency,
        ?Carbon $referenceDate = null,
    ): ?array {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $affiliationSnapshot = $this->affiliationSnapshot($affiliation, $planId, $coverageId, $paymentFrequency);

        $titularAmounts = $this->amountsForTitularAgeRange(
            $planId,
            $coverageId,
            $titularAgeRangeId,
            $paymentFrequency,
        );

        if ($titularAmounts === null) {
            return null;
        }

        $affiliates = $affiliation->affiliates()
            ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->get();

        $subtotalAnual = 0.0;
        $titularAnnual = $titularAmounts['annual_fee'];

        foreach ($affiliates as $affiliate) {
            if ($this->isTitular($affiliate)) {
                $subtotalAnual += $titularAnnual;

                continue;
            }

            $amounts = $this->calculator->calculateAffiliateAmountsForRenewal(
                $affiliationSnapshot,
                $affiliate,
                $referenceDate,
            );

            if ($amounts === null) {
                return null;
            }

            $subtotalAnual += $amounts['annual_fee'];
        }

        if ($affiliates->isEmpty()) {
            $subtotalAnual = $titularAnnual;
        }

        return [
            'subtotal_anual' => round($subtotalAnual, 2),
            'subtotal_quarterly' => round($subtotalAnual / 4, 2),
            'subtotal_biannual' => round($subtotalAnual / 2, 2),
            'subtotal_monthly' => round($subtotalAnual / 12, 2),
            'titular_annual' => round($titularAnnual, 2),
            'total_persons' => $affiliates->count() ?: 1,
        ];
    }

    public function previewFromRenovation(
        Renovation $renovation,
        int $planId,
        ?int $coverageId,
        int $ageRangeId,
        string $paymentFrequency,
    ): ?array {
        $affiliation = $renovation->affiliation;

        if ($affiliation === null) {
            return null;
        }

        $affiliation->loadMissing(['affiliates']);

        return $this->previewFamilyTotals(
            $affiliation,
            $planId,
            $coverageId,
            $ageRangeId,
            $paymentFrequency,
        );
    }

    public function resolveFee(int $planId, ?int $coverageId, int $ageRangeId): ?Fee
    {
        $query = Fee::query()->where('age_range_id', $ageRangeId);

        if ($planId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID) {
            return $query->first();
        }

        if ($coverageId === null) {
            return null;
        }

        return $query->where('coverage_id', $coverageId)->first();
    }

    private function affiliationSnapshot(
        Affiliation $affiliation,
        int $planId,
        ?int $coverageId,
        string $paymentFrequency,
    ): Affiliation {
        $snapshot = $affiliation->replicate();
        $snapshot->plan_id = $planId;
        $snapshot->coverage_id = $planId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID
            ? null
            : $coverageId;
        $snapshot->payment_frequency = $paymentFrequency;

        return $snapshot;
    }

    private function isTitular(Affiliate $affiliate): bool
    {
        return strtoupper((string) $affiliate->relationship) === 'TITULAR';
    }
}
