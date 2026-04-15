<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum FormaPago: string
{
    use ResolvesFromMixedState;

    case Credito = 'CREDITO';
    case CreditoPagado = 'CREDITO PAGADO';
    case TarjetaCredito = 'TARJETA DE CREDITO';

    public function label(): string
    {
        return match ($this) {
            self::Credito => 'Credito',
            self::CreditoPagado => 'Credito Pagado',
            self::TarjetaCredito => 'Tarjeta de Credito',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Credito => 'warning',
            self::CreditoPagado => 'success',
            self::TarjetaCredito => 'info',
        };
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

    protected static function legacyAliases(string $lower): ?self
    {
        return match ($lower) {
            'crédito' => self::Credito,
            'crédito pagado', 'credito pagado' => self::CreditoPagado,
            'tarjeta de crédito', 'tarjeta credito', 'tc', 'tdc' => self::TarjetaCredito,
            default => null,
        };
    }
}
