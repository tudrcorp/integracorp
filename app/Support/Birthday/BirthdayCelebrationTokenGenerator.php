<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use Illuminate\Support\Str;

final class BirthdayCelebrationTokenGenerator
{
    public static function generate(): string
    {
        return Str::lower(Str::random(48));
    }
}
