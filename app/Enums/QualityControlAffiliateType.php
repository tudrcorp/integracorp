<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A qué población pertenece una encuesta de Quality Control. Decide en qué ítem de
 * Operaciones se ve y contra qué afiliados se busca la cédula.
 */
enum QualityControlAffiliateType: string
{
    case Individual = 'INDIVIDUAL';

    case Corporate = 'CORPORATIVO';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individuales',
            self::Corporate => 'Corporativos',
        };
    }

    public function affiliateLabel(): string
    {
        return match ($this) {
            self::Individual => 'afiliado individual',
            self::Corporate => 'afiliado corporativo',
        };
    }
}
