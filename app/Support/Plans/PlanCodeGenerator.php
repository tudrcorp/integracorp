<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Models\Plan;

final class PlanCodeGenerator
{
    public static function next(): string
    {
        $nextSequence = (int) (Plan::query()->max('id') ?? 0) + 1;

        return 'TDEC-PL-000'.$nextSequence;
    }
}
