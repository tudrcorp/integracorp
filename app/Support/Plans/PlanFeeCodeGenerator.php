<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Models\Fee;

/**
 * Código correlativo de tarifa, con el mismo prefijo que ya usan las tarifas
 * cargadas a mano desde el catálogo (TDEC-FA-…).
 */
final class PlanFeeCodeGenerator
{
    public static function next(): string
    {
        $nextSequence = (int) (Fee::query()->max('id') ?? 0) + 1;

        return 'TDEC-FA-'.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
