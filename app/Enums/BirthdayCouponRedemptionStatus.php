<?php

declare(strict_types=1);

namespace App\Enums;

enum BirthdayCouponRedemptionStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Vigente',
            self::Used => 'Usado',
            self::Expired => 'Expirado',
        };
    }
}
