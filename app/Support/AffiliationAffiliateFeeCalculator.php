<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Fee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AffiliationAffiliateFeeCalculator
{
    public const INITIAL_PLAN_ID = 1;

    public const IDEAL_PLAN_ID = 2;

    public const SPECIAL_PLAN_ID = 3;

    public const NEGOTIATION_MESSAGE_IDEAL_OUT_OF_RANGE = 'La edad de uno o más afiliados está fuera de los rangos del Plan Ideal. Negocie con el cliente la adquisición del Plan Especial.';

    public function resolveAffiliateAge(Affiliate $affiliate): ?int
    {
        if (filled($affiliate->age)) {
            return (int) $affiliate->age;
        }

        if (blank($affiliate->birth_date)) {
            return null;
        }

        return Carbon::parse($affiliate->birth_date)->age;
    }

    public function isInitialPlanWithoutCoverage(Affiliation $affiliation): bool
    {
        return (int) $affiliation->plan_id === self::INITIAL_PLAN_ID;
    }

    public function isIdealPlan(Affiliation $affiliation): bool
    {
        return (int) $affiliation->plan_id === self::IDEAL_PLAN_ID;
    }

    public function ageMatchesConfiguredRange(int $age, AgeRange $ageRange): bool
    {
        if (filled($ageRange->age_init) && filled($ageRange->age_end)) {
            return $age >= (int) $ageRange->age_init
                && $age <= (int) $ageRange->age_end;
        }

        return $this->affiliateAgeMatchesFeeRangeLabel($age, (string) ($ageRange->range ?? ''));
    }

    public function affiliateAgeFitsPlanAgeRanges(int $planId, int $age, ?int $coverageId = null): bool
    {
        return $this->planAgeRanges($planId, $coverageId)
            ->contains(fn (AgeRange $range): bool => $this->ageMatchesConfiguredRange($age, $range));
    }

    /**
     * @param  iterable<Affiliate>  $affiliates
     * @return array{requires_negotiation: bool, message: string|null, out_of_range_affiliate_ids: list<int>}
     */
    public function evaluateIdealToSpecialPlanTransition(Affiliation $affiliation, iterable $affiliates): array
    {
        if (! $this->isIdealPlan($affiliation) || blank($affiliation->coverage_id)) {
            return [
                'requires_negotiation' => false,
                'message' => null,
                'out_of_range_affiliate_ids' => [],
            ];
        }

        $outOfRangeAffiliateIds = [];

        foreach ($affiliates as $affiliate) {
            $age = $this->resolveAffiliateAge($affiliate);

            if ($age === null) {
                continue;
            }

            if (! $this->affiliateAgeFitsPlanAgeRanges(self::IDEAL_PLAN_ID, $age, (int) $affiliation->coverage_id)) {
                $outOfRangeAffiliateIds[] = (int) $affiliate->id;
            }
        }

        if ($outOfRangeAffiliateIds === []) {
            return [
                'requires_negotiation' => false,
                'message' => null,
                'out_of_range_affiliate_ids' => [],
            ];
        }

        return [
            'requires_negotiation' => true,
            'message' => self::NEGOTIATION_MESSAGE_IDEAL_OUT_OF_RANGE,
            'out_of_range_affiliate_ids' => $outOfRangeAffiliateIds,
        ];
    }

    /**
     * @param  list<string>|null  $affiliateStatuses
     */
    public function applyIdealToSpecialPlanTransition(Affiliation $affiliation, ?array $affiliateStatuses = null): void
    {
        $affiliation->plan_id = self::SPECIAL_PLAN_ID;
        $affiliation->save();

        $statuses = $affiliateStatuses ?? ['ACTIVO', 'PRE-APROBADA'];

        $affiliation->affiliates()
            ->whereIn('status', $statuses)
            ->update(['plan_id' => self::SPECIAL_PLAN_ID]);
    }

    public function resolveFeeForAffiliateAge(Affiliation $affiliation, int $affiliateAge): ?Fee
    {
        $query = Fee::query()->with('ageRange');

        if ($this->isInitialPlanWithoutCoverage($affiliation)) {
            $query->where('age_range_id', 1);
        } else {
            $query->where('coverage_id', $affiliation->coverage_id);
        }

        $planId = (int) $affiliation->plan_id;

        return $query
            ->get()
            ->first(fn (Fee $fee): bool => $this->feeMatchesAffiliateAgeForPlan($affiliateAge, $fee, $planId));
    }

    public function resolveFeeForAffiliate(Affiliation $affiliation, Affiliate $affiliate): ?Fee
    {
        $affiliateAge = $this->resolveAffiliateAge($affiliate);

        if ($affiliateAge === null) {
            return null;
        }

        if (! $this->isInitialPlanWithoutCoverage($affiliation) && blank($affiliation->coverage_id)) {
            return null;
        }

        return $this->resolveFeeForAffiliateAge($affiliation, $affiliateAge);
    }

    /**
     * @return array{annual_fee: float, period_amount: float, age_range_id: int|null, coverage_id: int|null}|null
     */
    public function calculateAffiliateAmounts(Affiliation $affiliation, Affiliate $affiliate): ?array
    {
        $fee = $this->resolveFeeForAffiliate($affiliation, $affiliate);

        if ($fee === null) {
            return null;
        }

        $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $annualFee = (float) $fee->price;

        return [
            'annual_fee' => $annualFee,
            'period_amount' => $this->totalAmountForPaymentFrequency($annualFee, $paymentFrequency),
            'age_range_id' => $fee->age_range_id,
            'coverage_id' => $this->isInitialPlanWithoutCoverage($affiliation)
                ? null
                : $affiliation->coverage_id,
        ];
    }

    public function applyAmountsToAffiliate(Affiliation $affiliation, Affiliate $affiliate): bool
    {
        $amounts = $this->calculateAffiliateAmounts($affiliation, $affiliate);

        if ($amounts === null) {
            return false;
        }

        $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');

        $affiliate->update([
            'plan_id' => $affiliation->plan_id,
            'coverage_id' => $amounts['coverage_id'],
            'age_range_id' => $amounts['age_range_id'],
            'fee' => $amounts['annual_fee'],
            'total_amount' => $amounts['period_amount'],
            'payment_frequency' => $paymentFrequency,
        ]);

        return true;
    }

    public function recalculateAffiliationTotalsFromAffiliates(Affiliation $owner): void
    {
        $sumAnnualFees = (float) $owner->affiliates()
            ->where('status', 'ACTIVO')
            ->sum('fee');

        $frequency = (string) ($owner->payment_frequency ?? 'ANUAL');

        $owner->fee_anual = round($sumAnnualFees, 2);
        $owner->total_amount = $this->totalAmountForPaymentFrequency($owner->fee_anual, $frequency);
        $owner->family_members = $owner->affiliates()->where('status', 'ACTIVO')->count();
        $owner->save();
    }

    public function totalAmountForPaymentFrequency(float $annualFee, string $frequency): float
    {
        return match ($frequency) {
            'ANUAL' => round($annualFee, 2),
            'SEMESTRAL' => round($annualFee / 2, 2),
            'TRIMESTRAL' => round($annualFee / 4, 2),
            default => round($annualFee, 2),
        };
    }

    public function parseEffectiveDate(?string $effectiveDate): ?Carbon
    {
        if (blank($effectiveDate)) {
            return null;
        }

        $trimmed = trim((string) $effectiveDate);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $trimmed)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($trimmed)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function renewalDateFromEffectiveDate(?string $effectiveDate): ?Carbon
    {
        $parsed = $this->parseEffectiveDate($effectiveDate);

        if ($parsed === null) {
            return null;
        }

        return $parsed->copy()->addYear();
    }

    public function daysUntilRenewal(?string $effectiveDate, ?Carbon $today = null): ?int
    {
        $renewalDate = $this->renewalDateFromEffectiveDate($effectiveDate);

        if ($renewalDate === null) {
            return null;
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return (int) $today->diffInDays($renewalDate, absolute: false);
    }

    /**
     * @return Collection<int, AgeRange>
     */
    private function planAgeRanges(int $planId, ?int $coverageId = null): Collection
    {
        $query = AgeRange::query()->where('plan_id', $planId);

        if ($coverageId !== null) {
            $query->where(function ($builder) use ($coverageId): void {
                $builder->where('coverage_id', $coverageId)
                    ->orWhereNull('coverage_id');
            });
        }

        return $query->get();
    }

    private function feeMatchesAffiliateAgeForPlan(int $affiliateAge, Fee $fee, int $planId): bool
    {
        if (! $this->affiliateAgeMatchesFeeRange($affiliateAge, $fee)) {
            return false;
        }

        if ($planId === self::INITIAL_PLAN_ID) {
            return true;
        }

        $ageRange = $fee->ageRange;

        if ($ageRange === null) {
            return true;
        }

        return (int) $ageRange->plan_id === $planId;
    }

    private function affiliateAgeMatchesFeeRange(int $affiliateAge, Fee $fee): bool
    {
        $ageRange = $fee->ageRange;

        if ($ageRange !== null) {
            return $this->ageMatchesConfiguredRange($affiliateAge, $ageRange);
        }

        return $this->affiliateAgeMatchesFeeRangeLabel($affiliateAge, (string) ($fee->range ?? ''));
    }

    private function affiliateAgeMatchesFeeRangeLabel(int $affiliateAge, string $rangeLabel): bool
    {
        if (blank($rangeLabel)) {
            return false;
        }

        if (preg_match('/(\d+)\s*(?:a|–|-|—|hasta)\s*(\d+)/iu', $rangeLabel, $matches) === 1) {
            return $affiliateAge >= (int) $matches[1] && $affiliateAge <= (int) $matches[2];
        }

        return false;
    }
}
