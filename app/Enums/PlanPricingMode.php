<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

/**
 * Cómo se arma y se cobra un plan. Determina tanto el formulario que ve el
 * analista como la forma en que `AffiliationAffiliateFeeCalculator` resuelve la
 * tarifa de un afiliado.
 */
enum PlanPricingMode: string
{
    use ResolvesFromMixedState;

    /**
     * El plan tiene coberturas propias. Cada beneficio puede declarar un costo
     * límite por cobertura, y cada rango de edad una tarifa por cobertura.
     * La tarifa se resuelve por (plan, cobertura, rango de edad).
     */
    case Coberturas = 'COBERTURAS';

    /**
     * El plan agrupa beneficios como un todo, sin coberturas. Cada rango de
     * edad tiene una única tarifa, guardada con `fees.coverage_id` nulo.
     * La tarifa se resuelve por (plan, rango de edad).
     */
    case Paquete = 'PAQUETE';

    public function label(): string
    {
        return match ($this) {
            self::Coberturas => 'Plan con coberturas',
            self::Paquete => 'Paquete de beneficios',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Coberturas => 'El plan tiene montos de cobertura. Cada beneficio lleva un costo límite por cobertura y cada rango de edad una tarifa por cobertura.',
            self::Paquete => 'El plan agrupa beneficios como un todo, sin montos de cobertura. Cada rango de edad lleva una sola tarifa.',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Coberturas => 'info',
            self::Paquete => 'success',
        };
    }

    public function usesCoverages(): bool
    {
        return $this === self::Coberturas;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
