<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PrepareAffiliationCorporateRenovations;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\RenovationCorporate;
use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AcceptAffiliationCorporateRenovationsService
{
    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $calculator,
        private readonly AffiliationRenewalCollectionGenerator $renewalCollectionGenerator,
    ) {}

    /**
     * @param  Collection<int, RenovationCorporate>|EloquentCollection<int, RenovationCorporate>  $renovations
     */
    public function accept(
        Collection|EloquentCollection $renovations,
        string $acceptedBy,
        ?ManualRenovationAcceptanceOptions $manualOptions = null,
    ): AcceptAffiliationCorporateRenovationsResult {
        $accepted = 0;
        $skipped = 0;
        $messages = [];

        $renovations->loadMissing(['affiliationCorporate.corporateAffiliates']);

        foreach ($renovations as $renovation) {
            if ($renovation->status !== PrepareAffiliationCorporateRenovations::STATUS_RENOVATION_PERIOD) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: solo se aceptan registros en período de renovación.";

                continue;
            }

            $affiliation = $renovation->affiliationCorporate;

            if ($affiliation === null) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: afiliación corporativa no encontrada.";

                continue;
            }

            if ($affiliation->status !== PrepareAffiliationCorporateRenovations::AFFILIATION_STATUS_ACTIVE) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: la afiliación no está ACTIVA.";

                continue;
            }

            try {
                DB::transaction(function () use ($renovation, $affiliation, $acceptedBy, $manualOptions): void {
                    $this->acceptSingle($renovation, $affiliation, $acceptedBy, $manualOptions);
                });

                $accepted++;
            } catch (\Throwable $exception) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: {$exception->getMessage()}";

                Log::error('AcceptAffiliationCorporateRenovations: error al aceptar renovación', [
                    'renovation_corporate_id' => $renovation->id,
                    'affiliation_corporate_id' => $affiliation->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return new AcceptAffiliationCorporateRenovationsResult($accepted, $skipped, $messages);
    }

    private function acceptSingle(
        RenovationCorporate $renovation,
        AffiliationCorporate $affiliation,
        string $acceptedBy,
        ?ManualRenovationAcceptanceOptions $manualOptions,
    ): void {
        $acceptanceDate = Carbon::today()->startOfDay();
        $previousEffectiveDate = (string) $affiliation->effective_date;

        $affiliates = $affiliation->corporateAffiliates()
            ->whereIn('status', PrepareAffiliationCorporateRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->get();

        if ($manualOptions !== null) {
            $this->applyManualCommercialConfig($affiliation, $affiliates, $manualOptions, $acceptanceDate);
        } else {
            $this->applyProjectedRenovationConfig($renovation, $affiliation, $affiliates, $acceptanceDate);
        }

        $affiliation->refresh();
        $this->recalculateAffiliationTotals($affiliation);

        $newEffectiveDate = $renovation->date_renewal->format('d/m/Y');
        $affiliation->effective_date = $newEffectiveDate;
        $affiliation->save();

        $firstAffiliate = $affiliates->first();

        AffiliationCorporateRenovationHistory::query()->create(
            $this->historyAttributesFromAppliedState(
                $renovation,
                $affiliation->refresh(),
                $firstAffiliate,
                $acceptedBy,
                $previousEffectiveDate,
                $newEffectiveDate,
                $manualOptions,
            ),
        );

        $effectiveDate = $this->calculator->parseEffectiveDate($newEffectiveDate)
            ?? $renovation->date_renewal->copy()->startOfDay();

        $this->renewalCollectionGenerator->createPendingCollectionsForCorporateRenewal(
            $affiliation->refresh(),
            $effectiveDate,
            $acceptedBy,
        );

        $renovation->delete();
    }

    /**
     * @param  EloquentCollection<int, AffiliateCorporate>  $affiliates
     */
    private function applyManualCommercialConfig(
        AffiliationCorporate $affiliation,
        EloquentCollection $affiliates,
        ManualRenovationAcceptanceOptions $manualOptions,
        Carbon $acceptanceDate,
    ): void {
        $affiliation->payment_frequency = $manualOptions->paymentFrequency;
        $affiliation->save();

        foreach ($affiliates as $affiliate) {
            $age = $this->calculator->resolveAffiliateCorporateAgeForRenewal($affiliate, $acceptanceDate);

            if ($age === null) {
                continue;
            }

            $amounts = $this->calculator->calculateAmountsForPlanCoverageAndAge(
                $manualOptions->planId,
                $manualOptions->coverageId,
                $age,
                $manualOptions->paymentFrequency,
            );

            if ($amounts === null) {
                continue;
            }

            $affiliate->update([
                'plan_id' => $manualOptions->planId,
                'coverage_id' => $amounts['coverage_id'],
                'fee' => $amounts['annual_fee'],
                'subtotal_anual' => $amounts['annual_fee'],
                'subtotal_payment_frequency' => $amounts['period_amount'],
                'payment_frequency' => $manualOptions->paymentFrequency,
                'age' => $age,
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, AffiliateCorporate>  $affiliates
     */
    private function applyProjectedRenovationConfig(
        RenovationCorporate $renovation,
        AffiliationCorporate $affiliation,
        EloquentCollection $affiliates,
        Carbon $acceptanceDate,
    ): void {
        /** @var array{affiliates?: list<array<string, mixed>>, out_of_range_affiliate_ids?: list<int>} $info */
        $info = is_array($renovation->info_renovation) ? $renovation->info_renovation : [];
        $snapshotsById = collect($info['affiliates'] ?? [])->keyBy('affiliate_corporate_id');
        $outOfRangeIds = $info['out_of_range_affiliate_ids'] ?? [];
        $paymentFrequency = (string) ($renovation->payment_frequency ?? $affiliation->payment_frequency ?? 'ANUAL');

        $affiliation->payment_frequency = $paymentFrequency;
        $affiliation->save();

        foreach ($affiliates as $affiliate) {
            $snapshot = $snapshotsById->get($affiliate->id);
            $planId = (int) ($snapshot['plan_id'] ?? $affiliate->plan_id ?? $renovation->plan_id);
            $coverageId = filled($snapshot['coverage_id'] ?? null)
                ? (int) $snapshot['coverage_id']
                : (filled($affiliate->coverage_id) ? (int) $affiliate->coverage_id : null);

            if ($renovation->is_negotiation_candidate && in_array((int) $affiliate->id, $outOfRangeIds, true)) {
                $planId = AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID;
            }

            $age = $this->calculator->resolveAffiliateCorporateAgeForRenewal($affiliate, $acceptanceDate);
            $isInitial = $this->calculator->planHasNoCoverages($planId);
            $canRecalculate = $isInitial || $coverageId !== null;

            if ($canRecalculate && $age !== null) {
                $amounts = $this->calculator->calculateAmountsForPlanCoverageAndAge(
                    $planId,
                    $coverageId,
                    $age,
                    $paymentFrequency,
                );

                if ($amounts !== null) {
                    $affiliate->update([
                        'plan_id' => $planId,
                        'coverage_id' => $amounts['coverage_id'],
                        'fee' => $amounts['annual_fee'],
                        'subtotal_anual' => $amounts['annual_fee'],
                        'subtotal_payment_frequency' => $amounts['period_amount'],
                        'payment_frequency' => $paymentFrequency,
                        'age' => $age,
                    ]);

                    continue;
                }
            }

            $affiliate->update([
                'plan_id' => $planId,
                'payment_frequency' => $paymentFrequency,
                'age' => $age ?? $affiliate->age,
            ]);
        }
    }

    private function recalculateAffiliationTotals(AffiliationCorporate $affiliation): void
    {
        $sumAnnualFees = (float) $affiliation->corporateAffiliates()
            ->whereIn('status', PrepareAffiliationCorporateRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->sum('fee');

        $frequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $personCount = $affiliation->corporateAffiliates()
            ->whereIn('status', PrepareAffiliationCorporateRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->count();

        $affiliation->fee_anual = round($sumAnnualFees, 2);
        $affiliation->total_amount = $this->calculator->totalAmountForPaymentFrequency($sumAnnualFees, $frequency);
        $affiliation->poblation = $personCount;
        $affiliation->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function historyAttributesFromAppliedState(
        RenovationCorporate $renovation,
        AffiliationCorporate $affiliation,
        ?AffiliateCorporate $firstAffiliate,
        string $acceptedBy,
        string $previousEffectiveDate,
        string $newEffectiveDate,
        ?ManualRenovationAcceptanceOptions $manualOptions,
    ): array {
        $subtotalAnual = (float) ($affiliation->fee_anual ?? 0);
        $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $affiliateCount = $affiliation->corporateAffiliates()
            ->whereIn('status', PrepareAffiliationCorporateRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->count();

        return [
            'affiliation_corporate_id' => $affiliation->id,
            'affiliate_corporate_id' => $firstAffiliate?->id,
            'source_renovation_corporate_id' => $renovation->id,
            'accepted_at' => now(),
            'accepted_by' => $acceptedBy,
            'previous_effective_date' => $previousEffectiveDate !== '' ? $previousEffectiveDate : null,
            'new_effective_date' => $newEffectiveDate,
            'date_renewal' => $renovation->date_renewal,
            'remaining_days_at_accept' => $renovation->remaining_days,
            'status_at_accept' => $renovation->status,
            'code_affiliation' => $renovation->code_affiliation,
            'agent_id' => $renovation->agent_id,
            'code_agency' => $renovation->code_agency,
            'owner_code' => $renovation->owner_code,
            'owner_agent' => $renovation->owner_agent,
            'plan_id' => (int) ($renovation->plan_id ?? $firstAffiliate?->plan_id ?? 1),
            'coverage_id' => $renovation->coverage_id ?? $firstAffiliate?->coverage_id,
            'age_range_id' => (int) ($renovation->age_range_id ?? 1),
            'birth_date' => $renovation->birth_date,
            'age' => $renovation->age,
            'fee' => round((float) ($firstAffiliate?->fee ?? $subtotalAnual), 2),
            'subtotal_anual' => round($subtotalAnual, 2),
            'subtotal_quarterly' => round($subtotalAnual / 4, 2),
            'subtotal_biannual' => round($subtotalAnual / 2, 2),
            'subtotal_monthly' => round($subtotalAnual / 12, 2),
            'total_persons' => $affiliateCount > 0 ? $affiliateCount : $renovation->total_persons,
            'payment_frequency' => $paymentFrequency,
            'is_negotiation_candidate' => $manualOptions === null && $renovation->is_negotiation_candidate,
            'negotiation_notes' => $manualOptions === null
                ? $renovation->negotiation_notes
                : 'Renovación corporativa aceptada con configuración comercial manual.',
            'previous_plan_id' => $manualOptions === null ? $renovation->previous_plan_id : (int) ($renovation->plan_id ?? null),
        ];
    }
}
