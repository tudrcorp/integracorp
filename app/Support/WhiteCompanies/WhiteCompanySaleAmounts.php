<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Collection;
use App\Models\Sale;

final class WhiteCompanySaleAmounts
{
    /**
     * @return array{total_amount: float, white_company_neta: float|null}
     */
    public static function fromApproval(mixed $paidMembershipTotal, ?WhiteCompanyPaymentSettlement $settlement): array
    {
        return [
            'total_amount' => round((float) $paidMembershipTotal, 2),
            'white_company_neta' => $settlement instanceof WhiteCompanyPaymentSettlement
                ? $settlement->installmentNeta()
                : null,
        ];
    }

    public static function amountsEqual(mixed $left, mixed $right): bool
    {
        return abs(round((float) $left, 2) - round((float) $right, 2)) < 0.02;
    }

    /**
     * @return array{total_amount: float, white_company_neta: float}
     */
    public static function restoreHistoricalSale(mixed $currentSaleTotal, mixed $installmentNeta, mixed $paidMembershipTotal): array
    {
        $neta = round((float) $installmentNeta, 2);
        $saleTotal = round((float) $currentSaleTotal, 2);

        if (self::amountsEqual($saleTotal, $neta) && (float) $paidMembershipTotal > 0) {
            $saleTotal = round((float) $paidMembershipTotal, 2);
        }

        return [
            'total_amount' => $saleTotal,
            'white_company_neta' => $neta,
        ];
    }

    public static function restoreHistoricalCollection(mixed $currentCollectionTotal, mixed $installmentNeta, mixed $restoredSaleTotal): float
    {
        if (self::amountsEqual($currentCollectionTotal, $installmentNeta) && (float) $restoredSaleTotal > 0) {
            return round((float) $restoredSaleTotal, 2);
        }

        return round((float) $currentCollectionTotal, 2);
    }

    public static function installmentNetaForSale(Sale $sale): ?float
    {
        $affiliation = $sale->relationLoaded('affiliationByCode')
            ? $sale->affiliationByCode
            : $sale->affiliationByCode()->first();

        if ($affiliation === null || $affiliation->white_company_neta === null) {
            return null;
        }

        $frequency = filled($sale->payment_frequency)
            ? (string) $sale->payment_frequency
            : (string) ($affiliation->payment_frequency ?? '');

        $periods = max(1, WhiteCompanyPaymentSettlement::periodsForFrequency($frequency));

        return round((float) $affiliation->white_company_neta / $periods, 2);
    }

    public static function backfillAlliedSales(): int
    {
        $updated = 0;

        Sale::query()
            ->with(['affiliationByCode', 'paidMembershipIndividual', 'collections'])
            ->whereHas('affiliationByCode', function ($query): void {
                $query->whereNotNull('white_company_neta');
            })
            ->whereNull('white_company_neta')
            ->each(function (Sale $sale) use (&$updated): void {
                $installmentNeta = self::installmentNetaForSale($sale);

                if ($installmentNeta === null) {
                    return;
                }

                $paidTotal = $sale->paidMembershipIndividual?->total_amount
                    ?? $sale->affiliationByCode?->total_amount
                    ?? $sale->total_amount;

                $restored = self::restoreHistoricalSale(
                    $sale->total_amount,
                    $installmentNeta,
                    $paidTotal,
                );

                $sale->forceFill($restored)->save();
                $updated++;

                $sale->collections->each(function (Collection $collection) use ($installmentNeta, $restored): void {
                    $collectionAmount = self::restoreHistoricalCollection(
                        $collection->total_amount,
                        $installmentNeta,
                        $restored['total_amount'],
                    );

                    if (! self::amountsEqual($collection->total_amount, $collectionAmount)) {
                        $collection->forceFill(['total_amount' => $collectionAmount])->save();
                    }
                });
            });

        return $updated;
    }
}
