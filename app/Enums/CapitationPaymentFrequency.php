<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Frecuencia de pago de un plan de capitado (costo por afiliado).
 */
enum CapitationPaymentFrequency: string
{
    case Monthly = 'MENSUAL';

    case Quarterly = 'TRIMESTRAL';

    case Biannual = 'SEMESTRAL';

    case Annual = 'ANUAL';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Biannual => 'Semestral',
            self::Annual => 'Anual',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Biannual => 6,
            self::Annual => 12,
        };
    }

    /**
     * Costo mensual equivalente, para comparar planes de distinta frecuencia.
     */
    public function monthlyEquivalent(float $cost): float
    {
        return round($cost / $this->months(), 2);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
