<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCorporate;
use App\Models\RenovationCorporate;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prepara propuestas de renovación en `renovation_corporates` (solo lectura en el expediente vigente).
 */
class PrepareAffiliationCorporateRenovations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

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
        $this->runWithScheduledReport(
            'Renovaciones corporativas',
            function () use ($calculator): void {
                $today = ($this->runDate ?? Carbon::today())->copy()->startOfDay();
                $processed = 0;
                $upserted = 0;
                $inRenewalPeriod = 0;
                $affiliatesPriced = 0;
                $skippedNoEffectiveDate = 0;
                $missingCoverageWarnings = 0;
                $missingFeeWarnings = 0;

                ScheduledTaskRunReport::addExecutionDetail('Alcance', 'Afiliaciones corporativas con status ACTIVA');
                ScheduledTaskRunReport::addExecutionDetail('Período de renovación', '≤ '.self::RENEWAL_PERIOD_DAYS.' días antes del vencimiento');
                ScheduledTaskRunReport::addExecutionDetail('Fecha de cálculo', $today->format('d/m/Y'));
                ScheduledTaskRunReport::setFailureFootnote(
                    'Las fallas de cobertura o tarifa corresponden a afiliados en período de renovación con datos incompletos. No se duplican por canal.'
                );

                AffiliationCorporate::query()
                    ->where('status', self::AFFILIATION_STATUS_ACTIVE)
                    ->with(['corporateAffiliates' => fn ($query) => $query->whereIn('status', self::AFFILIATE_STATUSES_FOR_RENEWAL)])
                    ->chunkById(100, function ($affiliations) use ($calculator, $today, &$processed, &$upserted, &$inRenewalPeriod, &$affiliatesPriced, &$skippedNoEffectiveDate, &$missingCoverageWarnings, &$missingFeeWarnings): void {
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

                            $planTransition = $calculator->evaluateIdealToSpecialPlanTransitionForCorporateRenewal(
                                $affiliation->corporateAffiliates,
                                $today,
                            );
                            $isNegotiationCandidate = $planTransition['requires_negotiation'];
                            $negotiationNotes = $planTransition['message'];
                            $outOfRangeIds = $planTransition['out_of_range_affiliate_ids'];

                            $subtotalAnual = 0.0;
                            $affiliateCount = 0;
                            $representativePlanId = null;
                            $representativeCoverageId = null;
                            $representativeAgeRangeId = null;
                            $firstBirthDate = null;
                            $firstAge = null;
                            $firstAnnualFee = null;
                            $affiliateSnapshots = [];
                            $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');

                            foreach ($affiliation->corporateAffiliates as $affiliate) {
                                $affiliateCount++;
                                $planId = (int) ($affiliate->plan_id ?? 0);
                                $coverageId = filled($affiliate->coverage_id) ? (int) $affiliate->coverage_id : null;
                                $projectedPlanId = $planId;
                                $needsSpecial = $isNegotiationCandidate && in_array((int) $affiliate->id, $outOfRangeIds, true);

                                if ($needsSpecial) {
                                    $projectedPlanId = AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID;
                                }

                                $isInitial = $projectedPlanId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID;
                                $canRecalculateFees = $isInRenewalPeriod
                                    && ($isInitial || $coverageId !== null);

                                if ($isInRenewalPeriod && ! $canRecalculateFees && ! $isInitial) {
                                    $missingCoverageWarnings++;
                                    ScheduledTaskRunReport::recordFailure('Sin cobertura en período de renovación');
                                    Log::warning('PrepareAffiliationCorporateRenovations: sin cobertura, solo persiste conteo de días', [
                                        'affiliation_corporate_id' => $affiliation->id,
                                        'affiliate_corporate_id' => $affiliate->id,
                                        'code' => $affiliation->code,
                                    ]);
                                }

                                $annualFee = (float) ($affiliate->fee ?? 0);
                                $ageRangeId = null;
                                $age = $calculator->resolveAffiliateCorporateAgeForRenewal($affiliate, $today);
                                $birthDate = $calculator->parseBirthDate($affiliate->birth_date)?->toDateString();

                                if ($canRecalculateFees && $age !== null) {
                                    $amounts = $calculator->calculateAmountsForPlanCoverageAndAge(
                                        $projectedPlanId,
                                        $coverageId,
                                        $age,
                                        $paymentFrequency,
                                    );

                                    if ($amounts !== null) {
                                        $affiliatesPriced++;
                                        $annualFee = $amounts['annual_fee'];
                                        $ageRangeId = $amounts['age_range_id'];
                                        $coverageId = $amounts['coverage_id'];
                                    } else {
                                        $missingFeeWarnings++;
                                        ScheduledTaskRunReport::recordFailure('Tarifa no encontrada para afiliado corporativo');
                                        Log::warning('PrepareAffiliationCorporateRenovations: tarifa no encontrada', [
                                            'affiliation_corporate_id' => $affiliation->id,
                                            'affiliate_corporate_id' => $affiliate->id,
                                            'age' => $age,
                                        ]);
                                    }
                                }

                                $subtotalAnual += $annualFee;

                                if ($representativePlanId === null && $projectedPlanId > 0) {
                                    $representativePlanId = $projectedPlanId;
                                    $representativeCoverageId = $isInitial ? null : $coverageId;
                                    $representativeAgeRangeId = $ageRangeId;
                                    $firstBirthDate = $birthDate;
                                    $firstAge = $age;
                                    $firstAnnualFee = $annualFee;
                                }

                                $affiliateSnapshots[] = [
                                    'affiliate_corporate_id' => $affiliate->id,
                                    'plan_id' => $projectedPlanId,
                                    'previous_plan_id' => $needsSpecial ? $planId : null,
                                    'coverage_id' => $coverageId,
                                    'age_range_id' => $ageRangeId,
                                    'age' => $age,
                                    'birth_date' => $birthDate,
                                    'annual_fee' => round($annualFee, 2),
                                ];
                            }

                            if ($affiliateCount === 0) {
                                $subtotalAnual = (float) ($affiliation->fee_anual ?? 0);
                                $firstAnnualFee = $subtotalAnual;
                            }

                            $previousPlanId = null;
                            if ($isNegotiationCandidate) {
                                $previousPlanId = collect($affiliateSnapshots)
                                    ->pluck('previous_plan_id')
                                    ->filter()
                                    ->first() ?? AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID;
                                $representativePlanId = AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID;
                            }

                            RenovationCorporate::query()->updateOrCreate(
                                [
                                    'affiliation_corporate_id' => $affiliation->id,
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
                                    'owner_agent' => null,
                                    'plan_id' => (int) ($representativePlanId ?? 1),
                                    'coverage_id' => $representativeCoverageId,
                                    'is_negotiation_candidate' => $isNegotiationCandidate,
                                    'negotiation_notes' => $negotiationNotes,
                                    'previous_plan_id' => $previousPlanId,
                                    'age_range_id' => (int) ($representativeAgeRangeId ?? 1),
                                    'birth_date' => $firstBirthDate,
                                    'age' => $firstAge,
                                    'fee' => round($firstAnnualFee ?? $subtotalAnual, 2),
                                    'subtotal_anual' => round($subtotalAnual, 2),
                                    'subtotal_quarterly' => round($subtotalAnual / 4, 2),
                                    'subtotal_biannual' => round($subtotalAnual / 2, 2),
                                    'subtotal_monthly' => round($subtotalAnual / 12, 2),
                                    'total_persons' => $affiliateCount,
                                    'payment_frequency' => $paymentFrequency,
                                    'info_renovation' => [
                                        'affiliates' => $affiliateSnapshots,
                                        'out_of_range_affiliate_ids' => $outOfRangeIds,
                                    ],
                                    'created_by' => self::SYSTEM_ACTOR,
                                ],
                            );

                            $upserted++;
                        }
                    });

                ScheduledTaskRunReport::addMetric('Afiliaciones procesadas', $processed);
                ScheduledTaskRunReport::addMetric('Renovaciones upsert', $upserted);
                ScheduledTaskRunReport::addMetric('En período de renovación', $inRenewalPeriod);
                ScheduledTaskRunReport::addMetric('Afiliados tarificados', $affiliatesPriced);
                ScheduledTaskRunReport::addMetric('Omitidas sin fecha efectiva', $skippedNoEffectiveDate);
                ScheduledTaskRunReport::addMetric('Advertencias sin cobertura', $missingCoverageWarnings);
                ScheduledTaskRunReport::addMetric('Advertencias tarifa no encontrada', $missingFeeWarnings);

                Log::info('PrepareAffiliationCorporateRenovations: ejecución completada', [
                    'processed' => $processed,
                    'upserted' => $upserted,
                    'in_renewal_period' => $inRenewalPeriod,
                    'affiliates_priced_in_snapshot' => $affiliatesPriced,
                    'skipped_no_effective_date' => $skippedNoEffectiveDate,
                    'missing_coverage_warnings' => $missingCoverageWarnings,
                    'missing_fee_warnings' => $missingFeeWarnings,
                    'run_date' => $today->toDateString(),
                ]);
            },
            'Prepara o actualiza propuestas de renovación en renovation_corporates para afiliaciones corporativas activas, sin modificar el expediente vigente.',
            [
                '*Afiliaciones procesadas* = expedientes ACTIVA evaluados.',
                '*Renovaciones upsert* = filas creadas o actualizadas en renovation_corporates.',
                '*En período de renovación* = afiliaciones a ≤ '.self::RENEWAL_PERIOD_DAYS.' días del vencimiento.',
                'Las advertencias de cobertura/tarifa no impiden guardar el conteo de días, pero requieren revisión manual.',
            ],
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PrepareAffiliationCorporateRenovations: falló la ejecución', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
