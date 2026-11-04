<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

/**
 * Costo de capitación resuelto para un afiliado. `amount` es 0.0 siempre que no aplique,
 * y `reason` explica por qué en palabras del negocio.
 *
 * Con un ajuste negociado, `amount` es el negociado y `planAmount` el que daría el plan.
 */
final readonly class CapitationCostResult
{
    /**
     * @param  list<array{capitation_plan_id: int, plan: string, provider: string, cost: float, frequency: string}>  $lines
     * @param  array{amount: float, reason: string, by: string, at: string, plan_amount: float|null}|null  $override
     */
    public function __construct(
        public float $amount,
        public array $lines = [],
        public ?string $reason = null,
        public float $planAmount = 0.0,
        public ?array $override = null,
    ) {}

    public static function none(string $reason): self
    {
        return new self(0.0, [], $reason);
    }

    public function applies(): bool
    {
        return $this->lines !== [];
    }

    public function isOverridden(): bool
    {
        return $this->override !== null;
    }

    /**
     * El precio del plan cambió desde que se negoció el ajuste.
     */
    public function planChangedSinceOverride(): bool
    {
        return $this->override !== null
            && $this->override['plan_amount'] !== null
            && abs($this->override['plan_amount'] - $this->planAmount) >= 0.005;
    }

    /**
     * Texto corto bajo el monto: de dónde sale o por qué es cero.
     */
    public function summary(): string
    {
        if ($this->lines === []) {
            return (string) $this->reason;
        }

        $plans = implode(' + ', array_map(
            fn (array $line): string => $line['plan'].' · '.$line['provider'].' · '.mb_strtolower($line['frequency']),
            $this->lines,
        ));

        if ($this->override === null) {
            return $plans;
        }

        $text = 'Negociado · plan '.self::money($this->planAmount);

        if ($this->planChangedSinceOverride()) {
            $text .= ' (era '.self::money((float) $this->override['plan_amount']).' al negociar)';
        }

        return $text.' · '.$this->override['reason'];
    }

    public static function money(float $value): string
    {
        return number_format($value, 2, ',', '.').' US$';
    }
}
