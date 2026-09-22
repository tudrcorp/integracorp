<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\AffiliationCorporates\CorporatePaymentFrequency;

/**
 * Monto por período de una fila de `afilliation_corporate_plans` en los PDF
 * corporativos (avisos de cobro, avisos de pago y facturas).
 *
 * Las filas guardan `subtotal_biannual` (no `subtotal_semestral`, que nunca
 * existió) y pueden venir sin frecuencia; aquí se resuelven ambos casos.
 */
final class CorporateDocumentPlanAmounts
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function frequencyFor(array $row, ?string $fallbackFrequency = null): string
    {
        return CorporatePaymentFrequency::normalize((string) ($row['payment_frequency'] ?? ''))
            ?? CorporatePaymentFrequency::normalize($fallbackFrequency)
            ?? CorporatePaymentFrequency::ANUAL;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function periodAmount(array $row, ?string $fallbackFrequency = null): float
    {
        $annual = (float) ($row['subtotal_anual'] ?? 0);
        $frequency = self::frequencyFor($row, $fallbackFrequency);

        $stored = match ($frequency) {
            CorporatePaymentFrequency::SEMESTRAL => self::stored($row, ['subtotal_biannual', 'subtotal_semestral']),
            CorporatePaymentFrequency::TRIMESTRAL => self::stored($row, ['subtotal_quarterly']),
            CorporatePaymentFrequency::MENSUAL => self::stored($row, ['subtotal_monthly']),
            default => round($annual, 2),
        };

        /** Un subtotal en cero con anual positivo es un dato viejo sin calcular, no un monto real. */
        if ($stored === null || ($stored <= 0.0 && $annual > 0.0)) {
            return CorporatePaymentFrequency::periodAmount($annual, $frequency);
        }

        return $stored;
    }

    /**
     * Fecha «hasta» del período que cubre el documento, contada desde hoy.
     *
     * @param  array<string, mixed>  $row
     */
    public static function periodEndFromToday(array $row, ?string $fallbackFrequency = null): string
    {
        $months = CorporatePaymentFrequency::monthsPerInstallment(self::frequencyFor($row, $fallbackFrequency));

        return now()->addMonthsNoOverflow($months)->format('d/m/Y');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function stored(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return round((float) $row[$key], 2);
            }
        }

        return null;
    }
}
