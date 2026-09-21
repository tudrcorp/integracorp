<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlanPricingMode;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AffiliationAffiliateFeeCalculator
{
    public const INITIAL_PLAN_ID = 1;

    public const IDEAL_PLAN_ID = 2;

    public const SPECIAL_PLAN_ID = 3;

    public const NEGOTIATION_MESSAGE_IDEAL_OUT_OF_RANGE = 'La edad de uno o más afiliados está fuera de los rangos del Plan Ideal. Negocie con el cliente la adquisición del Plan Especial.';

    /**
     * Planes ya resueltos, para no repetir la consulta dentro de los bucles de
     * renovación, que recorren miles de afiliados.
     *
     * @var array<int, bool>
     */
    private array $benefitPackageCache = [];

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

    /**
     * Edad del afiliado a la fecha de corrida de renovación (prioriza fecha de nacimiento).
     */
    public function parseBirthDate(mixed $birthDate): ?Carbon
    {
        if (blank($birthDate)) {
            return null;
        }

        return $this->parseEffectiveDate((string) $birthDate);
    }

    public function resolveAffiliateAgeForRenewal(Affiliate $affiliate, Carbon $referenceDate): ?int
    {
        $birth = $this->parseBirthDate($affiliate->birth_date);

        if ($birth !== null) {
            $reference = $referenceDate->copy()->startOfDay();

            if ($reference->lt($birth)) {
                return 0;
            }

            return (int) $birth->diffInYears($reference);
        }

        if (filled($affiliate->age)) {
            return (int) $affiliate->age;
        }

        return null;
    }

    /**
     * Un paquete de beneficios no tiene coberturas: agrupa beneficios como un
     * todo y su tarifa depende solo del rango de edad.
     *
     * Antes esto era `plan_id === 1`. El número mágico dejaba fuera a cualquier
     * otro plan armado como paquete, que quedaba sin tarifa posible; ahora el
     * modo es una propiedad del plan (`plans.pricing_mode`). Se conserva el
     * plan 1 como respaldo por si la columna todavía no está poblada en algún
     * entorno.
     */
    public function planHasNoCoverages(?int $planId): bool
    {
        if ($planId === null) {
            return false;
        }

        if (array_key_exists($planId, $this->benefitPackageCache)) {
            return $this->benefitPackageCache[$planId];
        }

        $mode = PlanPricingMode::fromStored(
            Plan::query()->whereKey($planId)->value('pricing_mode'),
        );

        return $this->benefitPackageCache[$planId] = $mode !== null
            ? $mode === PlanPricingMode::Paquete
            : $planId === self::INITIAL_PLAN_ID;
    }

    /**
     * @deprecated Usar planHasNoCoverages(). Se mantiene porque lo llaman las
     *             afiliaciones, las renovaciones y las tarifas negociadas.
     */
    public function isInitialPlanWithoutCoverage(Affiliation $affiliation): bool
    {
        return $this->planHasNoCoverages((int) $affiliation->plan_id);
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
        return $this->resolveFeeForPlanCoverageAndAge(
            (int) $affiliation->plan_id,
            $this->isInitialPlanWithoutCoverage($affiliation) ? null : ($affiliation->coverage_id !== null ? (int) $affiliation->coverage_id : null),
            $affiliateAge,
            $this->isInitialPlanWithoutCoverage($affiliation),
        );
    }

    public function resolveFeeForPlanCoverageAndAge(
        int $planId,
        ?int $coverageId,
        int $affiliateAge,
        bool $isInitialPlanWithoutCoverage = false,
    ): ?Fee {
        // El plan se filtra en SQL contra `fees.plan_id`, la columna canónica del
        // catálogo. Antes se traían todas las tarifas de la cobertura y se
        // descartaban en PHP mirando `age_ranges.plan_id`, que además dejaba
        // pasar cualquier plan cuando el rango de edad no existía.
        $query = Fee::query()
            ->with('ageRange')
            ->forPlan($planId);

        if ($isInitialPlanWithoutCoverage || $this->planHasNoCoverages($planId)) {
            // En un paquete de beneficios la tarifa es plana por rango de edad,
            // y se guarda sin cobertura. Antes esto filtraba por
            // `age_range_id = 1`, lo que ataba el plan a un único rango.
            $query->whereNull('coverage_id');
        } else {
            if ($coverageId === null) {
                return null;
            }

            $query->where('coverage_id', $coverageId);
        }

        return $query
            ->get()
            ->first(fn (Fee $fee): bool => $this->feeMatchesAffiliateAgeForPlan($affiliateAge, $fee, $planId));
    }

    /**
     * @return array{annual_fee: float, period_amount: float, age_range_id: int|null, coverage_id: int|null}|null
     */
    public function calculateAmountsForPlanCoverageAndAge(
        int $planId,
        ?int $coverageId,
        int $affiliateAge,
        string $paymentFrequency,
    ): ?array {
        $isInitial = $planId === self::INITIAL_PLAN_ID;
        $fee = $this->resolveFeeForPlanCoverageAndAge($planId, $coverageId, $affiliateAge, $isInitial);

        if ($fee === null) {
            return null;
        }

        $annualFee = (float) $fee->price;

        return [
            'annual_fee' => $annualFee,
            'period_amount' => $this->totalAmountForPaymentFrequency($annualFee, $paymentFrequency),
            'age_range_id' => $fee->age_range_id,
            'coverage_id' => $isInitial ? null : $coverageId,
        ];
    }

    /**
     * Edad de afiliado corporativo a la fecha de corrida (prioriza fecha de nacimiento).
     */
    public function resolveAffiliateCorporateAgeForRenewal(AffiliateCorporate $affiliate, Carbon $referenceDate): ?int
    {
        $birth = $this->parseBirthDate($affiliate->birth_date);

        if ($birth !== null) {
            $reference = $referenceDate->copy()->startOfDay();

            if ($reference->lt($birth)) {
                return 0;
            }

            return (int) $birth->diffInYears($reference);
        }

        if (filled($affiliate->age)) {
            return (int) $affiliate->age;
        }

        return null;
    }

    /**
     * @param  iterable<AffiliateCorporate>  $affiliates
     * @return array{requires_negotiation: bool, message: string|null, out_of_range_affiliate_ids: list<int>}
     */
    public function evaluateIdealToSpecialPlanTransitionForCorporateRenewal(
        iterable $affiliates,
        Carbon $referenceDate,
    ): array {
        $outOfRangeAffiliateIds = [];

        foreach ($affiliates as $affiliate) {
            if ((int) ($affiliate->plan_id ?? 0) !== self::IDEAL_PLAN_ID) {
                continue;
            }

            if (blank($affiliate->coverage_id)) {
                continue;
            }

            $age = $this->resolveAffiliateCorporateAgeForRenewal($affiliate, $referenceDate);

            if ($age === null) {
                continue;
            }

            if (! $this->affiliateAgeFitsPlanAgeRanges(self::IDEAL_PLAN_ID, $age, (int) $affiliate->coverage_id)) {
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

        return $this->amountsFromResolvedFee($affiliation, $fee);
    }

    /**
     * Montos de renovación usando la edad calculada desde birth_date a la fecha de corrida.
     */
    public function calculateAffiliateAmountsForRenewal(
        Affiliation $affiliation,
        Affiliate $affiliate,
        Carbon $referenceDate,
    ): ?array {
        $affiliateAge = $this->resolveAffiliateAgeForRenewal($affiliate, $referenceDate);

        if ($affiliateAge === null) {
            return null;
        }

        if (! $this->isInitialPlanWithoutCoverage($affiliation) && blank($affiliation->coverage_id)) {
            return null;
        }

        $fee = $this->resolveFeeForAffiliateAge($affiliation, $affiliateAge);

        if ($fee === null) {
            return null;
        }

        return $this->amountsFromResolvedFee($affiliation, $fee);
    }

    /**
     * @return array{requires_negotiation: bool, message: string|null, out_of_range_affiliate_ids: list<int>}
     */
    public function evaluateIdealToSpecialPlanTransitionForRenewal(
        Affiliation $affiliation,
        iterable $affiliates,
        Carbon $referenceDate,
    ): array {
        if (! $this->isIdealPlan($affiliation) || blank($affiliation->coverage_id)) {
            return [
                'requires_negotiation' => false,
                'message' => null,
                'out_of_range_affiliate_ids' => [],
            ];
        }

        $outOfRangeAffiliateIds = [];

        foreach ($affiliates as $affiliate) {
            $age = $this->resolveAffiliateAgeForRenewal($affiliate, $referenceDate);

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
     * @return array{annual_fee: float, period_amount: float, age_range_id: int|null, coverage_id: int|null}
     */
    private function amountsFromResolvedFee(Affiliation $affiliation, Fee $fee): array
    {
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

    /**
     * Una tarifa sirve para un plan solo si `fees.plan_id` lo dice. Se comprueba
     * igual que en SQL para que el método siga siendo correcto cuando se lo
     * llama sobre tarifas que no vinieron de resolveFeeForPlanCoverageAndAge().
     *
     * Antes esto miraba `$fee->ageRange->plan_id` y devolvía true cuando el
     * rango de edad no existía, con lo cual una tarifa huérfana entraba en
     * cualquier plan que compartiera su cobertura y podía cobrarse el precio
     * equivocado. Sin plan_id la tarifa ahora no participa de ningún cálculo.
     */
    public function feeBelongsToPlan(Fee $fee, int $planId): bool
    {
        return $fee->plan_id !== null && (int) $fee->plan_id === $planId;
    }

    private function feeMatchesAffiliateAgeForPlan(int $affiliateAge, Fee $fee, int $planId): bool
    {
        if (! $this->feeBelongsToPlan($fee, $planId)) {
            return false;
        }

        return $this->affiliateAgeMatchesFeeRange($affiliateAge, $fee);
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
