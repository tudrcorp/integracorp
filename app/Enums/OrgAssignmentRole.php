<?php

declare(strict_types=1);

namespace App\Enums;

enum OrgAssignmentRole: string
{
    case Holder = 'TITULAR';
    case Interim = 'INTERINO';

    public function label(): string
    {
        return match ($this) {
            self::Holder => 'Titular',
            self::Interim => 'Interino',
        };
    }
}
