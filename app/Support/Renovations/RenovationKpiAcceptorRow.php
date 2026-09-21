<?php

declare(strict_types=1);

namespace App\Support\Renovations;

final readonly class RenovationKpiAcceptorRow
{
    public function __construct(
        public string $acceptedBy,
        public int $acceptedCount,
        public float $retainedPremium,
        public ?float $avgAnticipationDays,
    ) {}
}
