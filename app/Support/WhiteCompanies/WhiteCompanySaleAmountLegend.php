<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Affiliation;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

final class WhiteCompanySaleAmountLegend
{
    public static function forSale(Sale $sale, ?int $installmentNumber = null): ?string
    {
        $affiliation = self::affiliation($sale);

        if ($affiliation === null || $affiliation->white_company_neta === null) {
            return null;
        }

        $frequency = filled($sale->payment_frequency)
            ? (string) $sale->payment_frequency
            : (string) ($affiliation->payment_frequency ?? '');

        return self::format(
            (float) $affiliation->white_company_neta,
            $frequency,
            $installmentNumber ?? self::installmentNumber($sale),
        );
    }

    public static function format(float $annualNeta, string $paymentFrequency, int $installmentNumber): string
    {
        $periods = WhiteCompanyPaymentSettlement::periodsForFrequency($paymentFrequency);
        $neta = number_format($annualNeta, 2, ',', '.');
        $installment = self::cycleInstallment($installmentNumber, $periods);

        if ($periods === 1) {
            return "Neta total: {$neta} US$ · Pago único";
        }

        return "Neta total: {$neta} US$ · Cuota {$installment} de {$periods}";
    }

    private static function affiliation(Sale $sale): ?Affiliation
    {
        if ($sale->relationLoaded('affiliationByCode')) {
            return $sale->affiliationByCode;
        }

        if ($sale->relationLoaded('affiliation') && $sale->affiliation !== null) {
            return $sale->affiliation;
        }

        if (filled($sale->affiliation_code)) {
            return Affiliation::query()->where('code', $sale->affiliation_code)->first();
        }

        return $sale->affiliation;
    }

    private static function installmentNumber(Sale $sale): int
    {
        if (! $sale->exists || blank($sale->affiliation_code)) {
            return 1;
        }

        $createdAt = $sale->created_at;

        $count = Sale::query()
            ->where('affiliation_code', $sale->affiliation_code)
            ->where(function (Builder $query) use ($sale, $createdAt): void {
                if ($createdAt === null) {
                    $query->where('id', '<=', $sale->id);

                    return;
                }

                $query->where('created_at', '<', $createdAt)
                    ->orWhere(function (Builder $inner) use ($sale, $createdAt): void {
                        $inner->where('created_at', $createdAt)
                            ->where('id', '<=', $sale->id);
                    });
            })
            ->count();

        return max(1, $count);
    }

    private static function cycleInstallment(int $installmentNumber, int $periods): int
    {
        $safePeriods = max(1, $periods);
        $safeInstallment = max(1, $installmentNumber);

        return (($safeInstallment - 1) % $safePeriods) + 1;
    }
}
