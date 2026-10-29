<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator;

/**
 * Opciones del selector «Avisos de cobro» al aprobar un pago: cada aviso POR PAGAR con su fecha,
 * su número y su monto, y marcado cuando es un aviso por ajuste de población. Antes solo se veía
 * la fecha, y una cuota y un aviso por ajuste que vencen el mismo día eran indistinguibles.
 */
final class CollectionPaymentOptions
{
    /**
     * @return array<int, string>
     */
    public static function forAffiliationCode(?string $affiliationCode): array
    {
        if (blank($affiliationCode)) {
            return [];
        }

        $columns = ['id', 'collection_invoice_number', 'status', 'next_payment_date', 'filter_next_payment_date', 'total_amount'];

        if (Collection::hasBillingAdjustmentColumn()) {
            $columns[] = 'billing_adjustment_id';
        }

        return Collection::query()
            ->select($columns)
            ->where('affiliation_code', $affiliationCode)
            ->where('status', 'POR PAGAR')
            ->orderBy('filter_next_payment_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Collection $collection): array => [(int) $collection->id => self::label($collection)])
            ->all();
    }

    public static function label(Collection $collection): string
    {
        $parts = [
            (string) ($collection->next_payment_date ?: '—'),
            (string) ($collection->collection_invoice_number ?: '#'.$collection->id),
            PopulationBillingCalculator::money((int) round(((float) $collection->total_amount) * 100)),
        ];

        if ($collection->billing_adjustment_id !== null) {
            $parts[] = 'AJUSTE POR POBLACIÓN';
        }

        return implode(' · ', $parts);
    }
}
