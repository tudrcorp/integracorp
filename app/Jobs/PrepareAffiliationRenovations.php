<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Affiliate;
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

/**
 * Prepara propuestas de renovación en la tabla `renovations` (solo lectura en `affiliations` y `affiliates`).
 * El analista valida y aplica cambios manualmente; este job no debe alterar el expediente vigente.
 */
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
        $affiliatesPriced = 0;
        $skippedNoEffectiveDate = 0;

        Affiliation::query()
            ->where('status', self::AFFILIATION_STATUS_ACTIVE)
            ->with(['affiliates' => fn ($query) => $query->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)])
            ->chunkById(100, function ($affiliations) use ($calculator, $today, &$processed, &$upserted, &$inRenewalPeriod, &$affiliatesPriced, &$skippedNoEffectiveDate): void {
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
                        Log::warning('PrepareAffiliationRenovations: sin cobertura, solo persiste conteo de días en renovations', [
                            'affiliation_id' => $affiliation->id,
                            'code' => $affiliation->code,
                        ]);
                    }

                    $planTransition = $calculator->evaluateIdealToSpecialPlanTransitionForRenewal(
                        $affiliation,
                        $affiliation->affiliates,
                        $today,
                    );
                    $isNegotiationCandidate = $planTransition['requires_negotiation'];
                    $negotiationNotes = $planTransition['message'];
                    $previousPlanId = $isNegotiationCandidate ? (int) $affiliation->plan_id : null;

                    $renewalPlanId = $isNegotiationCandidate
                        ? AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID
                        : (int) $affiliation->plan_id;

                    $affiliationForFees = $this->affiliationSnapshotForFeeCalculation($affiliation, $isNegotiationCandidate);

                    $subtotalAnual = 0.0;
                    $titularAnnualFee = null;
                    $titularAgeRangeId = null;
                    $affiliateCount = 0;
                    $titularAffiliate = $this->resolveTitularAffiliate($affiliation);
                    $titularBirthDate = $titularAffiliate !== null
                        ? $calculator->parseBirthDate($titularAffiliate->birth_date)?->toDateString()
                        : null;
                    $titularAge = $titularAffiliate !== null
                        ? $calculator->resolveAffiliateAgeForRenewal($titularAffiliate, $today)
                        : null;

                    foreach ($affiliation->affiliates as $affiliate) {
                        if ($canRecalculateFees) {
                            $amounts = $calculator->calculateAffiliateAmountsForRenewal(
                                $affiliationForFees,
                                $affiliate,
                                $today,
                            );

                            if ($amounts !== null) {
                                $affiliatesPriced++;
                                $subtotalAnual += $amounts['annual_fee'];

                                if ($affiliate->relationship === 'TITULAR') {
                                    $titularAnnualFee = $amounts['annual_fee'];
                                    $titularAgeRangeId = $amounts['age_range_id'];
                                    $titularAge = $calculator->resolveAffiliateAgeForRenewal($affiliate, $today);
                                    $titularBirthDate = $calculator->parseBirthDate($affiliate->birth_date)?->toDateString()
                                        ?? $titularBirthDate;
                                }
                            } else {
                                Log::warning('PrepareAffiliationRenovations: tarifa no encontrada para afiliado (solo renovations)', [
                                    'affiliation_id' => $affiliation->id,
                                    'affiliate_id' => $affiliate->id,
                                    'age' => $calculator->resolveAffiliateAgeForRenewal($affiliate, $today),
                                ]);
                            }
                        } else {
                            $subtotalAnual += (float) $affiliate->fee;

                            if ($affiliate->relationship === 'TITULAR') {
                                $titularAnnualFee = (float) $affiliate->fee;
                                $titularAgeRangeId = $affiliate->age_range_id;
                                $titularAge = $calculator->resolveAffiliateAgeForRenewal($affiliate, $today) ?? $titularAge;
                                $titularBirthDate = $calculator->parseBirthDate($affiliate->birth_date)?->toDateString()
                                    ?? $titularBirthDate;
                            }
                        }

                        $affiliateCount++;
                    }

                    if ($affiliateCount === 0) {
                        $subtotalAnual = (float) ($affiliation->fee_anual ?? 0);
                        $titularAnnualFee = $subtotalAnual;
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
                            'birth_date' => $titularBirthDate,
                            'age' => $titularAge,
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
            'affiliates_priced_in_snapshot' => $affiliatesPriced,
            'skipped_no_effective_date' => $skippedNoEffectiveDate,
            'run_date' => $today->toDateString(),
        ]);
    }

    private function resolveTitularAffiliate(Affiliation $affiliation): ?Affiliate
    {
        $titular = $affiliation->affiliates->firstWhere('relationship', 'TITULAR');

        if ($titular !== null) {
            return $titular;
        }

        return $affiliation->affiliates->first();
    }

    /**
     * Copia en memoria para simular Plan Especial en la propuesta sin persistir en affiliations.
     */
    private function affiliationSnapshotForFeeCalculation(
        Affiliation $affiliation,
        bool $isNegotiationCandidate,
    ): Affiliation {
        if (! $isNegotiationCandidate) {
            return $affiliation;
        }

        $snapshot = $affiliation->replicate();
        $snapshot->plan_id = AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID;

        return $snapshot;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PrepareAffiliationRenovations: falló la ejecución', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
