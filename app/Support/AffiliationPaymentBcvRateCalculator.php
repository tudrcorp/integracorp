<?php

declare(strict_types=1);

namespace App\Support;

final class AffiliationPaymentBcvRateCalculator
{
    /**
     * Tasa Bs/USD implícita: monto en bolívares dividido entre el total en dólares.
     */
    public static function rateFromVesAndUsdTotal(mixed $vesAmount, mixed $totalUsdAmount): ?string
    {
        $ves = self::positiveAmount($vesAmount);
        $usd = self::positiveAmount($totalUsdAmount);
        if ($ves === null || $usd === null) {
            return null;
        }

        return self::formatRate($ves / $usd);
    }

    /**
     * Tasa Bs/USD cuando el tramo en dólares es solo la parte restante (p. ej. pago múltiple).
     */
    public static function rateFromVesAndRemainingUsd(mixed $vesAmount, mixed $remainingUsdAmount): ?string
    {
        $ves = self::positiveAmount($vesAmount);
        $usd = self::positiveAmount($remainingUsdAmount);
        if ($ves === null || $usd === null) {
            return null;
        }

        return self::formatRate($ves / $usd);
    }

    public static function positiveAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }

    public static function nonNegativeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float >= 0 ? $float : null;
    }

    private static function formatRate(float $rate): string
    {
        return (string) round($rate, 6);
    }
}
