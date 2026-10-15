<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PlanChange;

/**
 * Lo que pide el analista: plan, cobertura (si el plan la usa), frecuencia, rangos de edad
 * elegidos a mano por afiliado (id del afiliado => id del rango), el motivo y, si la autorizó,
 * la excepción a la regla de edad del Plan Ideal con su nota.
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
        public bool $allowIdealAgeException = false,
        public string $exceptionNote = '',
    ) {}

    /**
     * La misma solicitud con la excepción del Plan Ideal autorizada (o retirada).
     */
    public function withIdealAgeException(bool $allowed, string $note): self
    {
        return new self($this->planId, $this->coverageId, $this->frequency, $this->ageRangeOverrides, $this->reason, $allowed, $note);
    }

    /**
     * Solo cambia la frecuencia: conserva plan, cobertura y tarifas de cada afiliado.
     */
    public static function frequencyOnly(int $planId, ?int $coverageId, string $frequency, string $reason = ''): self
    {
        return new self($planId, $coverageId, $frequency, [], $reason);
    }
}
