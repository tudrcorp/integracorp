<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

/**
 * Frecuencias de pago de una afiliación y su equivalencia en meses y cuotas.
 */
final class CorporatePaymentFrequency
{
    public const ANUAL = 'ANUAL';

    public const SEMESTRAL = 'SEMESTRAL';

    public const TRIMESTRAL = 'TRIMESTRAL';

    public const MENSUAL = 'MENSUAL';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ANUAL => 'Anual',
            self::SEMESTRAL => 'Semestral',
            self::TRIMESTRAL => 'Trimestral',
            self::MENSUAL => 'Mensual',
        ];
    }

    /**
     * Valor canónico o null si no es una frecuencia válida.
     */
    public static function normalize(?string $frequency): ?string
    {
        $frequency = mb_strtoupper(trim((string) $frequency), 'UTF-8');

        return array_key_exists($frequency, self::options()) ? $frequency : null;
    }

    public static function label(?string $frequency): string
    {
        $normalized = self::normalize($frequency);

        return $normalized !== null ? self::options()[$normalized] : (filled($frequency) ? (string) $frequency : 'Sin frecuencia');
    }

    public static function monthsPerInstallment(string $frequency): int
    {
        return match (self::normalize($frequency)) {
            self::MENSUAL => 1,
            self::TRIMESTRAL => 3,
            self::SEMESTRAL => 6,
            default => 12,
        };
    }

    public static function installmentsPerYear(string $frequency): int
    {
        return intdiv(12, self::monthsPerInstallment($frequency));
    }

    public static function periodAmount(float $annualAmount, ?string $frequency): float
    {
        return round($annualAmount / self::installmentsPerYear((string) $frequency), 2);
    }
}
