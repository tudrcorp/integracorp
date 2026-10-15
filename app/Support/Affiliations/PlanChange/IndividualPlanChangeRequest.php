<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PlanChange;

/**
 * Lo que pide el analista: plan, cobertura (si el plan la usa), frecuencia, rangos de edad
 * elegidos a mano por afiliado (id del afiliado => id del rango) y el motivo.
 */
final readonly class IndividualPlanChangeRequest
{
    /**
     * @param  array<int, int>  $ageRangeOverrides
     */
    public function __construct(
        public int $planId,
        public ?int $coverageId,
        public string $frequency,
        public array $ageRangeOverrides = [],
        public string $reason = '',
    ) {}

    /**
     * Solo cambia la frecuencia: conserva plan, cobertura y tarifas de cada afiliado.
     */
    public static function frequencyOnly(int $planId, ?int $coverageId, string $frequency, string $reason = ''): self
    {
        return new self($planId, $coverageId, $frequency, [], $reason);
    }
}
