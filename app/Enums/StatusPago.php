<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum StatusPago: string
{
    use ResolvesFromMixedState;

    case Anulado = 'ANULADO';
    case Pagado = 'PAGADO';
    case Pendiente = 'PENDIENTE';

    public function label(): string
    {
        return match ($this) {
            self::Anulado => 'Anulado',
            self::Pagado => 'Pagado',
            self::Pendiente => 'Pendiente',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Anulado => 'danger',
            self::Pagado => 'success',
            self::Pendiente => 'warning',
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
            'abonado', 'confirmado' => self::Pagado,
            default => null,
        };
    }
}
