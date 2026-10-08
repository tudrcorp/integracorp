<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Database\Eloquent\Model;

/**
 * Pertenencia de un proveedor (natural o jurídico) al Departamento Médico
 * Outsourcing, con su tarifa de capitación: monto mensual en USD por cada
 * afiliado asignado.
 */
final class OutsourcingMedicalDepartment
{
    public const FLAG_COLUMN = 'is_outsourcing_medical_department';

    public const FEE_COLUMN = 'outsourcing_monthly_fee_per_affiliate_usd';

    public const MIN_FEE = 0.01;

    public const MAX_FEE = 99999999.99;

    /**
     * Un proveedor fuera de outsourcing no conserva tarifa: así nadie lee
     * después un monto que el analista ya dio por descartado.
     */
    public static function normalize(Model $provider): void
    {
        if (! (bool) $provider->getAttribute(self::FLAG_COLUMN)) {
            $provider->setAttribute(self::FLAG_COLUMN, false);
            $provider->setAttribute(self::FEE_COLUMN, null);
        }
    }

    public static function formatFee(mixed $fee): ?string
    {
        if ($fee === null || $fee === '' || ! is_numeric($fee)) {
            return null;
        }

        return 'US$ '.number_format((float) $fee, 2, ',', '.');
    }

    public static function summary(Model $provider): string
    {
        if (! (bool) $provider->getAttribute(self::FLAG_COLUMN)) {
            return 'No';
        }

        $fee = self::formatFee($provider->getAttribute(self::FEE_COLUMN));

        return $fee !== null ? $fee.' / afiliado / mes' : 'Sí (sin tarifa)';
    }
}
