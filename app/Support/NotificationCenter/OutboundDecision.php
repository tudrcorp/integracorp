<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;

/**
 * Resultado del guardián para un mensaje: a qué tipo pertenece, si es interno o
 * externo y, si no puede salir, por qué.
 */
final readonly class OutboundDecision
{
    public function __construct(
        public OutboundChannel $channel,
        public ?string $catalogKey,
        public NotificationAudience $audience,
        public ?string $origin,
        public ?string $blockedReason = null,
        public bool $critical = false,
        public bool $bulk = false,
        public ?string $recipientName = null,
    ) {}

    public function allowed(): bool
    {
        return $this->blockedReason === null;
    }

    public function blocked(): bool
    {
        return $this->blockedReason !== null;
    }
}
