<?php

declare(strict_types=1);

namespace App\Enums;

enum DressTaylorCompany: string
{
    case Tdec = 'TDEC';
    case Tdev = 'TDEV';

    public function label(): string
    {
        return match ($this) {
            self::Tdec => 'TDEC',
            self::Tdev => 'TDEV',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Tdec => 'info',
            self::Tdev => 'success',
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
}
