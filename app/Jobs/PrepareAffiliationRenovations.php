<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Affiliation;
use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepareAffiliationRenovations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Días antes del vencimiento (o después, si es negativo) en los que entra período de renovación. */
    public const RENEWAL_PERIOD_DAYS = 30;

    public const STATUS_VIGENTE = 'VIGENTE';

    public const STATUS_RENOVATION_PERIOD = 'PERIODO DE RENOVACION';

    public const SYSTEM_ACTOR = 'SISTEMA';

    public const AFFILIATION_STATUS_ACTIVE = 'ACTIVA';

    /** @var list<string> */
    public const AFFILIATE_STATUSES_FOR_RENEWAL = ['ACTIVO', 'PRE-APROBADA'];

    public function __construct(
        private readonly ?Carbon $runDate = null,
    ) {}

    public function handle(AffiliationAffiliateFeeCalculator $calculator): void
    {
        $today = ($this->runDate ?? Carbon::today())->copy()->startOfDay();
        $processed = 0;
        $upserted = 0;
        $inRenewalPeriod = 0;
        $affiliatesUpdated = 0;
        $skippedNoEffectiveDate = 0;

        Affiliation::query()
            ->where('status', self::AFFILIATION_STATUS_ACTIVE)
            ->with(['affiliates' => fn ($query) => $query->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)])
            ->chunkById(100, function ($affiliations) use ($calculator, $today, &$processed, &$upserted, &$inRenewalPeriod, &$affiliatesUpdated, &$skippedNoEffectiveDate): void {
                foreach ($affiliations as $affiliation) {
                    $processed++;

                    if (blank($affiliation->effective_date)) {
                        $skippedNoEffectiveDate++;

                        continue;
                    }

                    $daysUntilRenewal = $calculator->daysUntilRenewal($affiliation->effective_date, $today);

                    if ($daysUntilRenewal === null) {
                        $skippedNoEffectiveDate++;

                        continue;
                    }

                    $renewalDate = $calculator->renewalDateFromEffectiveDate($affiliation->effective_date);

                    if ($renewalDate === null) {
                        $skippedNoEffectiveDate++;

                        continue;
                    }

                    $isInRenewalPeriod = $daysUntilRenewal <= self::RENEWAL_PERIOD_DAYS;
                    $status = $isInRenewalPeriod
                        ? self::STATUS_RENOVATION_PERIOD
                        : self::STATUS_VIGENTE;

                    if ($isInRenewalPeriod) {
                        $inRenewalPeriod++;
                    }

                    $canRecalculateFees = $isInRenewalPeriod
                        && ($calculator->isInitialPlanWithoutCoverage($affiliation) || filled($affiliation->coverage_id));

                    if ($isInRenewalPeriod && ! $canRecalculateFees) {
                        Log::warning('PrepareAffiliationRenovations: sin cobertura, solo actualiza conteo de días', [
                            'affiliation_id' => $affiliation->id,
                            'code' => $affiliation->code,
                        ]);
                    }

                    $planTransition = $calculator->evaluateIdealToSpecialPlanTransition(
                        $affiliation,
                        $affiliation->affiliates,
                    );
                    $isNegotiationCandidate = $planTransition['requires_negotiation'];
                    $negotiationNotes = $planTransition['message'];
                    $previousPlanId = null;

                    if ($isNegotiationCandidate && $canRecalculateFees) {
                        $previousPlanId = (int) $affiliation->plan_id;
                        $calculator->applyIdealToSpecialPlanTransition(
                            $affiliation,
                            self::AFFILIATE_STATUSES_FOR_RENEWAL,
                        );
                        $affiliation->refresh();
                        $affiliation->load(['affiliates' => fn ($query) => $query->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)]);

                        Log::info('PrepareAffiliationRenovations: transición Plan Ideal a Plan Especial', [
                            'affiliation_id' => $affiliation->id,
                            'code' => $affiliation->code,
                            'out_of_range_affiliate_ids' => $planTransition['out_of_range_affiliate_ids'],
                        ]);
                    }

                    $renewalPlanId = $isNegotiationCandidate
                        ? AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID
                        : (int) $affiliation->plan_id;

                    $subtotalAnual = 0.0;
                    $titularAnnualFee = null;
                    $titularAgeRangeId = null;
                    $affiliateCount = 0;

                    foreach ($affiliation->affiliates as $affiliate) {
                        if ($canRecalculateFees) {
                            if ($calculator->applyAmountsToAffiliate($affiliation, $affiliate)) {
                                $affiliate->refresh();
                                $affiliatesUpdated++;
                                $subtotalAnual += (float) $affiliate->fee;

                                if ($affiliate->relationship === 'TITULAR') {
                                    $titularAnnualFee = (float) $affiliate->fee;
                                    $titularAgeRangeId = $affiliate->age_range_id;
                                }
                            } else {
                                Log::warning('PrepareAffiliationRenovations: tarifa no encontrada para afiliado', [
                                    'affiliation_id' => $affiliation->id,
                                    'affiliate_id' => $affiliate->id,
                                    'age' => $calculator->resolveAffiliateAge($affiliate),
                                ]);
                            }
                        } else {
                            $subtotalAnual += (float) $affiliate->fee;

                            if ($affiliate->relationship === 'TITULAR') {
                                $titularAnnualFee = (float) $affiliate->fee;
                                $titularAgeRangeId = $affiliate->age_range_id;
                            }
                        }

                        $affiliateCount++;
                    }

                    if ($affiliateCount === 0) {
                        $subtotalAnual = (float) ($affiliation->fee_anual ?? 0);
                        $titularAnnualFee = $subtotalAnual;
                    }

                    if ($canRecalculateFees && $affiliateCount > 0) {
                        $this->recalculateAffiliationTotalsFromRenewalAffiliates($affiliation, $calculator);
                        $affiliation->refresh();
                        $subtotalAnual = (float) $affiliation->fee_anual;
                    }

                    $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');

                    Renovation::query()->updateOrCreate(
                        [
                            'affiliation_id' => $affiliation->id,
                            'date_renewal' => $renewalDate->toDateString(),
                        ],
                        [
                            'remaining_days' => $daysUntilRenewal,
                            'status' => $status,
                            'updated_by' => self::SYSTEM_ACTOR,
                            'code_affiliation' => (string) $affiliation->code,
                            'agent_id' => (string) ($affiliation->agent_id ?? ''),
                            'code_agency' => (string) ($affiliation->code_agency ?? ''),
                            'owner_code' => $affiliation->owner_code,
                            'owner_agent' => $affiliation->owner_agent,
                            'plan_id' => $renewalPlanId,
                            'coverage_id' => $calculator->isInitialPlanWithoutCoverage($affiliation)
                                ? null
                                : $affiliation->coverage_id,
                            'is_negotiation_candidate' => $isNegotiationCandidate,
                            'negotiation_notes' => $negotiationNotes,
                            'previous_plan_id' => $previousPlanId,
                            'age_range_id' => (int) ($titularAgeRangeId ?? $affiliation->affiliates->first()?->age_range_id ?? 1),
                            'fee' => round($titularAnnualFee ?? $subtotalAnual, 2),
                            'subtotal_anual' => round($subtotalAnual, 2),
                            'subtotal_quarterly' => round($subtotalAnual / 4, 2),
                            'subtotal_biannual' => round($subtotalAnual / 2, 2),
                            'subtotal_monthly' => round($subtotalAnual / 12, 2),
                            'total_persons' => $affiliateCount,
                            'payment_frequency' => $paymentFrequency,
                            'created_by' => self::SYSTEM_ACTOR,
                        ],
                    );

                    $upserted++;
                }
            });

        Log::info('PrepareAffiliationRenovations: ejecución completada', [
            'processed' => $processed,
            'upserted' => $upserted,
            'in_renewal_period' => $inRenewalPeriod,
            'affiliates_updated' => $affiliatesUpdated,
            'skipped_no_effective_date' => $skippedNoEffectiveDate,
            'run_date' => $today->toDateString(),
        ]);
    }

    private function recalculateAffiliationTotalsFromRenewalAffiliates(
        Affiliation $affiliation,
        AffiliationAffiliateFeeCalculator $calculator,
    ): void {
        $sumAnnualFees = (float) $affiliation->affiliates()
            ->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->sum('fee');

        $frequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');

        $affiliation->fee_anual = round($sumAnnualFees, 2);
        $affiliation->total_amount = $calculator->totalAmountForPaymentFrequency($affiliation->fee_anual, $frequency);
        $affiliation->family_members = $affiliation->affiliates()
            ->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->count();
        $affiliation->save();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PrepareAffiliationRenovations: falló la ejecución', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
