<?php

declare(strict_types=1);

namespace App\Support\Renovations;

final readonly class RenovationKpiSnapshot
{
    /**
     * @param  list<RenovationKpiAcceptorRow>  $acceptors
     */
    public function __construct(
        public string $periodLabel,
        public bool $isCorporate,
        public int $acceptedCount,
        public float $retainedPremium,
        public ?float $avgAnticipationDays,
        public int $overdueOpenCount,
        public int $inWindowOpenCount,
        public ?float $retentionRate,
        public ?float $churnRate,
        public array $acceptors,
    ) {}

    public function acceptedLabel(): string
    {
        return $this->isCorporate ? 'Empresas aceptadas' : 'Pólizas aceptadas';
    }

    public function unitLabel(): string
    {
        return $this->isCorporate ? 'Empresas' : 'Pólizas';
    }

    public function formattedAcceptedCount(): string
    {
        return number_format($this->acceptedCount, 0, ',', '.');
    }

    public function formattedRetention(): string
    {
        return self::formatRate($this->retentionRate);
    }

    public function formattedChurn(): string
    {
        return self::formatRate($this->churnRate);
    }

    public function formattedPremium(): string
    {
        return 'US$ '.number_format($this->retainedPremium, 2, ',', '.');
    }

    public function formattedAnticipation(): string
    {
        if ($this->avgAnticipationDays === null) {
            return '—';
        }

        return ((string) (int) round($this->avgAnticipationDays)).' días';
    }

    public function formattedInWindow(): string
    {
        return number_format($this->inWindowOpenCount, 0, ',', '.');
    }

    public function formattedOverdue(): string
    {
        return number_format($this->overdueOpenCount, 0, ',', '.');
    }

    public static function formatRate(?float $rate): string
    {
        if ($rate === null) {
            return '—';
        }

        return ((string) (int) round($rate * 100)).' %';
    }
}
