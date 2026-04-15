<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum StatusVaucher: string
{
    use ResolvesFromMixedState;

    case Activo = 'ACTIVO';
    case Anulado = 'ANULADO';
    case Expirado = 'EXPIRADO';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Anulado => 'Anulado',
            self::Expirado => 'Expirado',
        };
    }

    /**
     * Color de badge Filament (success, danger, warning, gray, info, primary).
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Anulado => 'danger',
            self::Expirado => 'warning',
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
            'vigente' => self::Activo,
            'cancelado' => self::Anulado,
            default => null,
        };
    }
}
