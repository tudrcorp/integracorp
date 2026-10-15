<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Una afiliación individual no puede cambiar de plan ahora. El mensaje se muestra tal cual
 * al analista: dice el motivo y qué hacer para destrabarlo.
 *
 * `rule` identifica los bloqueos que admiten una excepción autorizada (con nota y traza);
 * los demás protegen la cobranza y no se pueden saltar.
 */
class IndividualPlanChangeBlockedException extends RuntimeException
{
    public const RULE_IDEAL_AGE_RANGE = 'ideal_age_range';

    public function __construct(string $message, public readonly ?string $rule = null)
    {
        parent::__construct($message);
    }

    public function allowsException(): bool
    {
        return $this->rule === self::RULE_IDEAL_AGE_RANGE;
    }
}
