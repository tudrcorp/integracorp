<?php

declare(strict_types=1);

namespace App\Services;

final readonly class ManualRenovationAcceptanceOptions
{
    public function __construct(
        public int $planId,
        public ?int $coverageId,
        public int $ageRangeId,
        public string $paymentFrequency,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): ?self
    {
        if (! ($data['manual_commercial_config'] ?? false)) {
            return null;
        }

        $planId = (int) ($data['plan_id'] ?? 0);
        $ageRangeId = (int) ($data['age_range_id'] ?? 0);
        $paymentFrequency = (string) ($data['payment_frequency'] ?? '');

        if ($planId <= 0 || $ageRangeId <= 0 || $paymentFrequency === '') {
            throw new \InvalidArgumentException('Debe completar plan, rango de edad y frecuencia de pago.');
        }

        $coverageId = filled($data['coverage_id'] ?? null)
            ? (int) $data['coverage_id']
            : null;

        if ($planId !== 1 && $coverageId === null) {
            throw new \InvalidArgumentException('Debe seleccionar una cobertura para el plan elegido.');
        }

        return new self(
            planId: $planId,
            coverageId: $coverageId,
            ageRangeId: $ageRangeId,
            paymentFrequency: $paymentFrequency,
        );
    }
}
