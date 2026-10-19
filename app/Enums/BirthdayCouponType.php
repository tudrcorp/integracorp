<?php

declare(strict_types=1);

namespace App\Enums;

enum BirthdayCouponType: string
{
    case Viajes = 'viajes';
    case Farmadoc = 'farmadoc';
    case Kit = 'kit';
    case Casa = 'casa';

    public function label(): string
    {
        return match ($this) {
            self::Viajes => 'Tu Doctor en Viajes',
            self::Farmadoc => 'Farmadoc',
            self::Kit => 'Kit corporativo',
            self::Casa => 'Tu Doctor en Casa (referidor)',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Viajes,
            self::Farmadoc,
            self::Kit,
            self::Casa,
        ];
    }
}
