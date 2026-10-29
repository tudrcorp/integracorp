<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Support\AffiliationCorporates\CorporatePaymentFrequency;

/**
 * Cómo se cobran los upgrades (decidido por Negocios, 29/10/2026): son anuales y se pagan una
 * sola vez, completos, en el primer pago del año, sea cual sea la frecuencia. Las demás cuotas
 * llevan solo el plan. El total del año no cambia; cambia cómo se reparte.
 *
 * Si se agregan a una afiliación ya en curso, van completos en el próximo aviso por pagar.
 */
final class UpgradeInstallments
{
    public const RULE = 'Los upgrades se pagan una sola vez al año, completos, en el primer pago.';

    /**
     * Cuota regular: solo el plan, repartido según la frecuencia.
     */
    public static function regular(float $planAnnual, ?string $frequency): float
    {
        $cents = (int) round(max(0.0, $planAnnual) * 100);

        return round(intdiv($cents * 2 + self::installments($frequency), self::installments($frequency) * 2) / 100, 2);
    }

    /**
     * Primer pago del año: la cuota regular más los upgrades completos.
     */
    public static function first(float $planAnnual, float $upgradesAnnual, ?string $frequency): float
    {
        return round(self::regular($planAnnual, $frequency) + max(0.0, $upgradesAnnual), 2);
    }

    /**
     * Calendario del año por frecuencia: primer pago, pagos siguientes y cuántos son.
     *
     * @param  list<string>  $frequencies
     * @return array<string, array{label: string, installments: int, first: float, next: ?float}>
     */
    public static function schedule(float $planAnnual, float $upgradesAnnual, array $frequencies = [CorporatePaymentFrequency::ANUAL, CorporatePaymentFrequency::SEMESTRAL, CorporatePaymentFrequency::TRIMESTRAL]): array
    {
        $schedule = [];

        foreach ($frequencies as $frequency) {
            $installments = self::installments($frequency);
            $schedule[$frequency] = [
                'label' => CorporatePaymentFrequency::label($frequency),
                'installments' => $installments,
                'first' => self::first($planAnnual, $upgradesAnnual, $frequency),
                'next' => $installments > 1 ? self::regular($planAnnual, $frequency) : null,
            ];
        }

        return $schedule;
    }

    public static function installments(?string $frequency): int
    {
        return CorporatePaymentFrequency::installmentsPerYear(CorporatePaymentFrequency::normalize($frequency) ?? CorporatePaymentFrequency::ANUAL);
    }
}
