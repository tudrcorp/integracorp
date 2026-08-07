<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskBusinessTicketCreationVerdict
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $message,
        public readonly ?string $denialReason = null,
    ) {}

    public function shouldShowCreateTicketButton(): bool
    {
        return $this->allowed;
    }

    public static function allowed(?string $message = null): self
    {
        return new self(
            allowed: true,
            message: $message ?? 'Puede crear tickets.',
        );
    }

    public static function denied(string $message, ?string $denialReason = null): self
    {
        return new self(
            allowed: false,
            message: $message,
            denialReason: $denialReason,
        );
    }
}
