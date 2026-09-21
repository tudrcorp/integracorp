<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Exceptions\WhiteCompanyNegotiatedRateMissingException;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Fee;
use App\Models\WhiteCompany;
use App\Models\WhiteCompanyFee;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\CreditReconciliations\CreditReconciliationAffiliationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class WhiteCompanyNegotiatedRateResolver
{
    public function __construct(
        private AffiliationAffiliateFeeCalculator $feeCalculator = new AffiliationAffiliateFeeCalculator,
    ) {}

    /**
     * Null si la afiliación no pertenece a una empresa aliada.
     * Lanza si es aliada y falta neta/precio para alguna persona.
     */
    public function settlementForAffiliation(Affiliation $affiliation): ?WhiteCompanyPaymentSettlement
    {
        $company = CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode(
            is_string($affiliation->code_agency) ? $affiliation->code_agency : null
        );

        if (! $company instanceof WhiteCompany) {
            return null;
        }

        $settlement = $this->fromSnapshot($affiliation, $company)
            ?? $this->fromMatrix($affiliation, $company);

        if ($settlement === null) {
            throw WhiteCompanyNegotiatedRateMissingException::make(
                (string) $company->name,
                is_string($affiliation->code) ? $affiliation->code : null,
                $affiliation->plan_id !== null ? (int) $affiliation->plan_id : null,
                $affiliation->coverage_id !== null ? (int) $affiliation->coverage_id : null,
            );
        }

        $this->snapshot($affiliation, $settlement);

        return $settlement;
    }

    private function fromSnapshot(Affiliation $affiliation, WhiteCompany $company): ?WhiteCompanyPaymentSettlement
    {
        if ($affiliation->white_company_sale_price === null || $affiliation->white_company_neta === null) {
            return null;
        }

        return new WhiteCompanyPaymentSettlement(
            annualSalePrice: (float) $affiliation->white_company_sale_price,
            annualNeta: (float) $affiliation->white_company_neta,
            paymentFrequency: (string) ($affiliation->payment_frequency ?? 'ANUAL'),
            whiteCompanyId: (int) $company->id,
            feeId: $affiliation->white_company_fee_id !== null ? (int) $affiliation->white_company_fee_id : null,
        );
    }

    private function fromMatrix(Affiliation $affiliation, WhiteCompany $company): ?WhiteCompanyPaymentSettlement
    {
        $affiliates = $this->affiliatesForSettlement($affiliation);
        $rates = $this->activeRatesByFeeId((int) $company->id);

        /** @var list<array{sale_price: float, neta: float, fee_id: int}> $lines */
        $lines = [];

        if ($affiliates->isEmpty()) {
            $line = $this->lineForTitular($affiliation, $company, $rates);

            $persons = max(1, (int) ($affiliation->family_members ?? 1));

            for ($i = 0; $i < $persons; $i++) {
                $lines[] = $line;
            }
        } else {
            foreach ($affiliates as $affiliate) {
                $lines[] = $this->lineForAffiliate($affiliation, $affiliate, $company, $rates);
            }
        }

        if ($lines === []) {
            return null;
        }

        return WhiteCompanyPaymentSettlement::fromPersonLines(
            $lines,
            (string) ($affiliation->payment_frequency ?? 'ANUAL'),
            (int) $company->id,
        );
    }

    /**
     * @return Collection<int, Affiliate>
     */
    private function affiliatesForSettlement(Affiliation $affiliation): Collection
    {
        if ($affiliation->relationLoaded('affiliates')) {
            return $affiliation->affiliates;
        }

        return $affiliation->affiliates()->get();
    }

    /**
     * @return Collection<int|string, WhiteCompanyFee>
     */
    private function activeRatesByFeeId(int $whiteCompanyId): Collection
    {
        return WhiteCompanyFee::query()
            ->where('white_company_id', $whiteCompanyId)
            ->where('status', 'ACTIVO')
            ->get()
            ->keyBy('fee_id');
    }

    /**
     * @param  Collection<int|string, WhiteCompanyFee>  $rates
     * @return array{sale_price: float, neta: float, fee_id: int}
     */
    private function lineForTitular(Affiliation $affiliation, WhiteCompany $company, Collection $rates): array
    {
        $planId = (int) ($affiliation->plan_id ?? 0);
        $isInitial = $this->feeCalculator->isInitialPlanWithoutCoverage($affiliation);
        $coverageId = $isInitial ? null : ($affiliation->coverage_id !== null ? (int) $affiliation->coverage_id : null);
        $age = $this->titularAge($affiliation);

        return $this->lineForPlanCoverageAndAge(
            $affiliation,
            $company,
            $rates,
            $planId,
            $coverageId,
            $age,
            $isInitial,
            trim((string) $affiliation->full_name_ti) !== '' ? (string) $affiliation->full_name_ti : 'titular',
        );
    }

    /**
     * @param  Collection<int|string, WhiteCompanyFee>  $rates
     * @return array{sale_price: float, neta: float, fee_id: int}
     */
    private function lineForAffiliate(
        Affiliation $affiliation,
        Affiliate $affiliate,
        WhiteCompany $company,
        Collection $rates,
    ): array {
        $planId = (int) ($affiliate->plan_id ?: $affiliation->plan_id ?: 0);
        $isInitial = $this->feeCalculator->planHasNoCoverages($planId);
        $coverageId = $isInitial
            ? null
            : ($affiliate->coverage_id !== null
                ? (int) $affiliate->coverage_id
                : ($affiliation->coverage_id !== null ? (int) $affiliation->coverage_id : null));
        $age = $this->feeCalculator->resolveAffiliateAge($affiliate);

        $personName = trim((string) $affiliate->full_name);
        if ($personName === '') {
            $personName = 'afiliado #'.(string) $affiliate->getKey();
        }

        return $this->lineForPlanCoverageAndAge(
            $affiliation,
            $company,
            $rates,
            $planId,
            $coverageId,
            $age,
            $isInitial,
            $personName,
        );
    }

    /**
     * @param  Collection<int|string, WhiteCompanyFee>  $rates
     * @return array{sale_price: float, neta: float, fee_id: int}
     */
    private function lineForPlanCoverageAndAge(
        Affiliation $affiliation,
        WhiteCompany $company,
        Collection $rates,
        int $planId,
        ?int $coverageId,
        ?int $age,
        bool $isInitial,
        string $personName,
    ): array {
        $fee = $planId > 0
            ? $this->feeCalculator->resolveFeeForPlanCoverageAndAge(
                $planId,
                $coverageId,
                $age ?? 0,
                $isInitial,
            )
            : null;

        $rate = $fee instanceof Fee ? $rates->get($fee->id) : null;

        if (! $rate instanceof WhiteCompanyFee) {
            throw WhiteCompanyNegotiatedRateMissingException::forPerson(
                (string) $company->name,
                is_string($affiliation->code) ? $affiliation->code : null,
                $personName,
                $planId > 0 ? $planId : null,
                $coverageId,
                $age,
            );
        }

        return [
            'sale_price' => (float) $rate->sale_price,
            'neta' => (float) $rate->neta,
            'fee_id' => (int) $fee->id,
        ];
    }

    private function titularAge(Affiliation $affiliation): ?int
    {
        if (filled($affiliation->birth_date_ti)) {
            $birth = $this->feeCalculator->parseBirthDate($affiliation->birth_date_ti);

            if ($birth instanceof Carbon) {
                return (int) $birth->age;
            }
        }

        if (filled($affiliation->age) && is_numeric($affiliation->age)) {
            return (int) $affiliation->age;
        }

        return null;
    }

    private function snapshot(Affiliation $affiliation, WhiteCompanyPaymentSettlement $settlement): void
    {
        $needsSnapshot = $affiliation->white_company_sale_price === null
            || $affiliation->white_company_neta === null;

        if (! $needsSnapshot) {
            return;
        }

        $affiliation->white_company_sale_price = $settlement->annualSalePrice;
        $affiliation->white_company_neta = $settlement->annualNeta;
        $affiliation->white_company_fee_id = $settlement->feeId;
        $affiliation->save();
    }
}
