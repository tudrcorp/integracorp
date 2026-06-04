<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PrepareAffiliationRenovations;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationRenovationHistory;
use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Filament\Renovations\RenovationManualAcceptancePricing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AcceptAffiliationRenovationsService
{
    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $calculator,
        private readonly RenovationManualAcceptancePricing $manualPricing,
    ) {}

    /**
     * @param  Collection<int, Renovation>|EloquentCollection<int, Renovation>  $renovations
     */
    public function accept(
        Collection|EloquentCollection $renovations,
        string $acceptedBy,
        ?ManualRenovationAcceptanceOptions $manualOptions = null,
    ): AcceptAffiliationRenovationsResult {
        $accepted = 0;
        $skipped = 0;
        $messages = [];

        $renovations->loadMissing(['affiliation.affiliates']);

        foreach ($renovations as $renovation) {
            if ($renovation->status !== PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: solo se aceptan registros en período de renovación.";

                continue;
            }

            $affiliation = $renovation->affiliation;

            if ($affiliation === null) {
                $skipped++;
                $messages[] = "Renovación {$renovation->code_affiliation}: afiliación no encontrada.";

                continue;
            }

            if ($affiliation->status !== PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE) {
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

                Log::error('AcceptAffiliationRenovations: error al aceptar renovación', [
                    'renovation_id' => $renovation->id,
                    'affiliation_id' => $affiliation->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return new AcceptAffiliationRenovationsResult($accepted, $skipped, $messages);
    }

    private function acceptSingle(
        Renovation $renovation,
        Affiliation $affiliation,
        string $acceptedBy,
        ?ManualRenovationAcceptanceOptions $manualOptions,
    ): void {
        $acceptanceDate = Carbon::today()->startOfDay();
        $previousEffectiveDate = (string) $affiliation->effective_date;

        $affiliates = $affiliation->affiliates()
            ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->get();

        if ($manualOptions !== null) {
            $this->applyManualCommercialConfig($affiliation, $affiliates, $manualOptions, $acceptanceDate);
        } else {
            $this->applyProjectedRenovationConfig($renovation, $affiliation, $affiliates, $acceptanceDate);
        }

        $affiliation->refresh();
        $this->calculator->recalculateAffiliationTotalsFromAffiliates($affiliation);

        $newEffectiveDate = $renovation->date_renewal->format('d/m/Y');
        $affiliation->effective_date = $newEffectiveDate;

        $titular = $this->resolveTitularAffiliate(
            $affiliation->affiliates()
                ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
                ->get(),
        );

        if ($titular !== null && $renovation->age !== null) {
            $affiliation->age = $renovation->age;
            $titular->age = $renovation->age;
            $titular->save();
        }

        if ($titular !== null && $renovation->birth_date !== null) {
            $birthFormatted = $renovation->birth_date->format('d/m/Y');
            $affiliation->birth_date_ti = $birthFormatted;
            $titular->birth_date = $birthFormatted;
            $titular->save();
        }

        $affiliation->save();

        AffiliationRenovationHistory::query()->create(
            $this->historyAttributesFromAppliedState(
                $renovation,
                $affiliation->refresh(),
                $titular,
                $acceptedBy,
                $previousEffectiveDate,
                $newEffectiveDate,
                $manualOptions,
            ),
        );

        $renovation->delete();
    }

    /**
     * @param  EloquentCollection<int, Affiliate>  $affiliates
     */
    private function applyManualCommercialConfig(
        Affiliation $affiliation,
        EloquentCollection $affiliates,
        ManualRenovationAcceptanceOptions $manualOptions,
        Carbon $acceptanceDate,
    ): void {
        $affiliation->plan_id = $manualOptions->planId;
        $affiliation->coverage_id = $manualOptions->planId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID
            ? null
            : $manualOptions->coverageId;
        $affiliation->payment_frequency = $manualOptions->paymentFrequency;
        $affiliation->save();

        $affiliationSnapshot = $this->affiliationSnapshotForManualConfig($affiliation, $manualOptions);
        $titularAmounts = $this->manualPricing->amountsForTitularAgeRange(
            $manualOptions->planId,
            $manualOptions->coverageId,
            $manualOptions->ageRangeId,
            $manualOptions->paymentFrequency,
        );

        if ($titularAmounts === null) {
            throw new \RuntimeException('No se encontró tarifa para el plan, cobertura y rango de edad seleccionados.');
        }

        foreach ($affiliates as $affiliate) {
            if ($this->isTitular($affiliate)) {
                $affiliate->update([
                    'plan_id' => $manualOptions->planId,
                    'coverage_id' => $titularAmounts['coverage_id'],
                    'age_range_id' => $titularAmounts['age_range_id'],
                    'fee' => $titularAmounts['annual_fee'],
                    'total_amount' => $titularAmounts['period_amount'],
                    'payment_frequency' => $manualOptions->paymentFrequency,
                ]);

                continue;
            }

            $this->applyRenewalAmountsToAffiliate($affiliationSnapshot, $affiliate, $acceptanceDate);
        }
    }

    /**
     * @param  EloquentCollection<int, Affiliate>  $affiliates
     */
    private function applyProjectedRenovationConfig(
        Renovation $renovation,
        Affiliation $affiliation,
        EloquentCollection $affiliates,
        Carbon $acceptanceDate,
    ): void {
        if ($renovation->is_negotiation_candidate) {
            $this->calculator->applyIdealToSpecialPlanTransition(
                $affiliation,
                PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL,
            );
            $affiliation->refresh();
            $affiliates = $affiliation->affiliates()
                ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
                ->get();
        } elseif ((int) $affiliation->plan_id !== (int) $renovation->plan_id) {
            $affiliation->plan_id = (int) $renovation->plan_id;
            $affiliation->save();

            $affiliation->affiliates()
                ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
                ->update(['plan_id' => (int) $renovation->plan_id]);
        }

        $affiliationForFees = $this->affiliationSnapshotForFeeCalculation(
            $affiliation,
            (bool) $renovation->is_negotiation_candidate,
        );

        $canRecalculateFees = $this->calculator->isInitialPlanWithoutCoverage($affiliation)
            || filled($affiliation->coverage_id);

        foreach ($affiliates as $affiliate) {
            if ($canRecalculateFees) {
                $this->applyRenewalAmountsToAffiliate($affiliationForFees, $affiliate, $acceptanceDate);
            }
        }
    }

    /**
     * @param  EloquentCollection<int, Affiliate>  $affiliates
     */
    private function resolveTitularAffiliate(EloquentCollection $affiliates): ?Affiliate
    {
        $titular = $affiliates->firstWhere('relationship', 'TITULAR');

        return $titular ?? $affiliates->first();
    }

    private function isTitular(Affiliate $affiliate): bool
    {
        return strtoupper((string) $affiliate->relationship) === 'TITULAR';
    }

    private function applyRenewalAmountsToAffiliate(
        Affiliation $affiliationForFees,
        Affiliate $affiliate,
        Carbon $acceptanceDate,
    ): void {
        $amounts = $this->calculator->calculateAffiliateAmountsForRenewal(
            $affiliationForFees,
            $affiliate,
            $acceptanceDate,
        );

        if ($amounts === null) {
            return;
        }

        $paymentFrequency = (string) ($affiliationForFees->payment_frequency ?? 'ANUAL');

        $affiliate->update([
            'plan_id' => $affiliationForFees->plan_id,
            'coverage_id' => $amounts['coverage_id'],
            'age_range_id' => $amounts['age_range_id'],
            'fee' => $amounts['annual_fee'],
            'total_amount' => $amounts['period_amount'],
            'payment_frequency' => $paymentFrequency,
        ]);
    }

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

    private function affiliationSnapshotForManualConfig(
        Affiliation $affiliation,
        ManualRenovationAcceptanceOptions $manualOptions,
    ): Affiliation {
        $snapshot = $affiliation->replicate();
        $snapshot->plan_id = $manualOptions->planId;
        $snapshot->coverage_id = $manualOptions->planId === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID
            ? null
            : $manualOptions->coverageId;
        $snapshot->payment_frequency = $manualOptions->paymentFrequency;

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function historyAttributesFromAppliedState(
        Renovation $renovation,
        Affiliation $affiliation,
        ?Affiliate $titular,
        string $acceptedBy,
        string $previousEffectiveDate,
        string $newEffectiveDate,
        ?ManualRenovationAcceptanceOptions $manualOptions,
    ): array {
        $subtotalAnual = (float) ($affiliation->fee_anual ?? 0);
        $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $affiliateCount = $affiliation->affiliates()
            ->whereIn('status', PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->count();

        return [
            'affiliation_id' => $affiliation->id,
            'affiliate_id' => $titular?->id,
            'source_renovation_id' => $renovation->id,
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
            'plan_id' => (int) $affiliation->plan_id,
            'coverage_id' => $affiliation->coverage_id,
            'age_range_id' => (int) ($titular?->age_range_id ?? $renovation->age_range_id),
            'birth_date' => $renovation->birth_date,
            'age' => $renovation->age,
            'fee' => round((float) ($titular?->fee ?? $subtotalAnual), 2),
            'subtotal_anual' => round($subtotalAnual, 2),
            'subtotal_quarterly' => round($subtotalAnual / 4, 2),
            'subtotal_biannual' => round($subtotalAnual / 2, 2),
            'subtotal_monthly' => round($subtotalAnual / 12, 2),
            'total_persons' => $affiliateCount > 0 ? $affiliateCount : $renovation->total_persons,
            'payment_frequency' => $paymentFrequency,
            'is_negotiation_candidate' => $manualOptions === null && $renovation->is_negotiation_candidate,
            'negotiation_notes' => $manualOptions === null
                ? $renovation->negotiation_notes
                : 'Renovación aceptada con configuración comercial manual.',
            'previous_plan_id' => $manualOptions === null ? $renovation->previous_plan_id : (int) ($renovation->plan_id ?? null),
        ];
    }
}

final class AcceptAffiliationRenovationsResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public readonly int $accepted,
        public readonly int $skipped,
        public readonly array $messages,
    ) {}
}
