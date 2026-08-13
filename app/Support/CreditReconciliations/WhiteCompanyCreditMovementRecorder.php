<?php

declare(strict_types=1);

namespace App\Support\CreditReconciliations;

use App\Enums\CreditReconciliationEntityType;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CreditReconciliation;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\WhiteCompany;
use Illuminate\Support\Facades\Auth;

final class WhiteCompanyCreditMovementRecorder
{
    public static function recordIndividualInstallment(PaidMembership $membership, ?Collection $collection = null): ?CreditReconciliation
    {
        $affiliation = $membership->affiliation;

        if (! $affiliation instanceof Affiliation) {
            return null;
        }

        $company = CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode(
            is_string($affiliation->code_agency) ? $affiliation->code_agency : null
        );

        if (! $company instanceof WhiteCompany) {
            return null;
        }

        if (self::alreadyRecorded($membership->id, null, $collection?->id)) {
            return null;
        }

        $snapshot = CreditReconciliationAffiliationSnapshot::fromIndividual($affiliation);

        return self::store(
            $company,
            $snapshot,
            (float) ($collection?->total_amount ?? $membership->total_amount ?? 0),
            $collection?->collection_invoice_number,
            $membership->id,
            null,
            $collection?->id,
        );
    }

    public static function recordCorporateInstallment(PaidMembershipCorporate $membership, ?Collection $collection = null): ?CreditReconciliation
    {
        $affiliation = $membership->affiliation_corporate;

        if (! $affiliation instanceof AffiliationCorporate) {
            return null;
        }

        $company = CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode(
            is_string($affiliation->code_agency) ? $affiliation->code_agency : null
        );

        if (! $company instanceof WhiteCompany) {
            return null;
        }

        if (self::alreadyRecorded(null, $membership->id, $collection?->id)) {
            return null;
        }

        $snapshot = CreditReconciliationAffiliationSnapshot::fromCorporate($affiliation);

        return self::store(
            $company,
            $snapshot,
            (float) ($collection?->total_amount ?? $membership->total_amount ?? 0),
            $collection?->collection_invoice_number,
            null,
            $membership->id,
            $collection?->id,
        );
    }

    /**
     * @param  array<int|string, mixed>  $collectionIds
     */
    public static function recordIndividualCollections(PaidMembership $membership, array $collectionIds): void
    {
        foreach ($collectionIds as $collectionId) {
            $collection = Collection::query()->find($collectionId);

            if (! $collection instanceof Collection) {
                continue;
            }

            self::recordIndividualInstallment($membership, $collection);
        }
    }

    /**
     * @param  array<int|string, mixed>  $collectionIds
     */
    public static function recordCorporateCollections(PaidMembershipCorporate $membership, array $collectionIds): void
    {
        foreach ($collectionIds as $collectionId) {
            $collection = Collection::query()->find($collectionId);

            if (! $collection instanceof Collection) {
                continue;
            }

            self::recordCorporateInstallment($membership, $collection);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function store(
        WhiteCompany $company,
        array $snapshot,
        float $totalToPay,
        ?string $collectionInvoiceNumber,
        ?int $paidMembershipId,
        ?int $paidMembershipCorporateId,
        ?int $collectionId,
    ): CreditReconciliation {
        return CreditReconciliation::query()->create([
            ...$snapshot,
            'entity_type' => CreditReconciliationEntityType::WhiteCompany,
            'white_company_id' => $company->id,
            'total_to_pay' => $totalToPay,
            'collection_invoice_number' => $collectionInvoiceNumber,
            'paid_membership_id' => $paidMembershipId,
            'paid_membership_corporate_id' => $paidMembershipCorporateId,
            'collection_id' => $collectionId,
            'created_by' => Auth::user()?->name,
        ]);
    }

    private static function alreadyRecorded(?int $paidMembershipId, ?int $paidMembershipCorporateId, ?int $collectionId): bool
    {
        return CreditReconciliation::query()
            ->when(
                $collectionId !== null,
                fn ($query) => $query->where('collection_id', $collectionId),
                function ($query) use ($paidMembershipId, $paidMembershipCorporateId) {
                    $query->whereNull('collection_id');

                    if ($paidMembershipId !== null) {
                        $query->where('paid_membership_id', $paidMembershipId);
                    }

                    if ($paidMembershipCorporateId !== null) {
                        $query->where('paid_membership_corporate_id', $paidMembershipCorporateId);
                    }
                }
            )
            ->exists();
    }
}
