<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

enum OperationInventoryProductPresentation: string
{
    use ResolvesFromMixedState;

    case Caja = 'CAJA';
    case Unidad = 'UNIDAD';

    public function label(): string
    {
        return match ($this) {
            self::Caja => 'Caja',
            self::Unidad => 'Unidad',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Caja => 'info',
            self::Unidad => 'success',
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
            'caja', 'box' => self::Caja,
            'unidad', 'unit', 'und' => self::Unidad,
            default => null,
        };
    }
}
