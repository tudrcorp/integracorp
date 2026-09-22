<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Jobs\CreateAvisoDeCobro;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;
use Illuminate\Support\Facades\Auth;

/**
 * Genera en cola el PDF del aviso de cobro de cobros corporativos ya guardados.
 *
 * El archivo se nombra por número de aviso (`ADP-{número}.pdf`), así que volver
 * a despachar un aviso existente lo reemplaza con los montos vigentes.
 */
final class CorporateCollectionNoticeDispatcher
{
    /**
     * @param  list<int>  $collectionIds
     * @return int avisos despachados
     */
    public static function dispatch(AffiliationCorporate $owner, array $collectionIds): int
    {
        $user = Auth::user();

        if ($collectionIds === [] || $user === null) {
            return 0;
        }

        $owner = $owner->fresh(['affiliationCorporatePlans']);

        if ($owner === null) {
            return 0;
        }

        $affiliatesCount = AffiliationDocumentAffiliatesCount::forCorporate($owner);
        $plans = $owner->affiliationCorporatePlans->toArray();
        $dispatched = 0;

        BillingCollection::query()
            ->whereIn('id', $collectionIds)
            ->orderBy('id')
            ->get()
            ->each(function (BillingCollection $collection) use ($owner, $affiliatesCount, $plans, $user, &$dispatched): void {
                if (blank($collection->collection_invoice_number)) {
                    return;
                }

                $frequency = CorporatePaymentFrequency::normalize($collection->payment_frequency)
                    ?? CorporatePaymentFrequency::normalize($owner->payment_frequency)
                    ?? CorporatePaymentFrequency::ANUAL;

                CreateAvisoDeCobro::dispatch([
                    'invoice_number' => $collection->collection_invoice_number,
                    'emission_date' => $collection->next_payment_date,
                    'full_name_ti' => $collection->affiliate_full_name ?: $owner->name_corporate,
                    'ci_rif_ti' => $owner->rif,
                    'address_ti' => $owner->address,
                    'phone_ti' => $owner->phone,
                    'email_ti' => $owner->email,
                    'total_amount' => (float) $collection->total_amount,
                    'plan' => $plans,
                    'upgrades' => CorporateAffiliateUpgradeManager::noticeLinesFor($owner, $frequency),
                    'coverage' => null,
                    'frequency' => $frequency,
                    'affiliates_count' => $affiliatesCount,
                ], $user)->afterCommit();

                $dispatched++;
            });

        return $dispatched;
    }
}
