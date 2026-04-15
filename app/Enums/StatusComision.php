<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum StatusComision: string
{
    use ResolvesFromMixedState;

    case Anulado = 'ANULADO';
    case Compensado = 'COMPENSADO';
    case EnGestion = 'EN GESTION';
    case Pagado = 'PAGADO';
    case Pendiente = 'PENDIENTE';

    public function label(): string
    {
        return match ($this) {
            self::Anulado => 'Anulado',
            self::Compensado => 'Compensado',
            self::EnGestion => 'En gestión',
            self::Pagado => 'Pagado',
            self::Pendiente => 'Pendiente',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Anulado => 'danger',
            self::Compensado => 'info',
            self::EnGestion => 'warning',
            self::Pagado => 'success',
            self::Pendiente => 'gray',
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
            'pagada', 'liquidada' => self::Pagado,
            'en gestion', 'en_gestion' => self::EnGestion,
            default => null,
        };
    }
}
