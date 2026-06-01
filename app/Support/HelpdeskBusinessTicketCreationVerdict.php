<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpdeskGroup;

final class HelpdeskBusinessTicketCreationVerdict
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $message,
        public readonly ?HelpdeskGroup $group = null,
        public readonly int $used = 0,
        public readonly int $quota = 0,
        public readonly bool $bypassesQuota = false,
        public readonly ?string $denialReason = null,
    ) {}

    public function shouldShowCreateTicketButton(): bool
    {
        if ($this->allowed || $this->bypassesQuota) {
            return true;
        }

        if ($this->denialReason === HelpdeskBusinessTicketCreationDenialReason::UNAUTHENTICATED) {
            return false;
        }

        return $this->denialReason !== HelpdeskBusinessTicketCreationDenialReason::QUOTA_EXHAUSTED;
    }

    public static function allowed(
        ?HelpdeskGroup $group = null,
        int $used = 0,
        int $quota = 0,
        ?string $bypassReason = null,
    ): self {
        return new self(
            allowed: true,
            message: $bypassReason ?? 'Puede crear tickets.',
            group: $group,
            used: $used,
            quota: $quota,
            bypassesQuota: $bypassReason !== null,
        );
    }

    public static function denied(
        string $message,
        ?HelpdeskGroup $group = null,
        int $used = 0,
        int $quota = 0,
        ?string $denialReason = null,
    ): self {
        return new self(
            allowed: false,
            message: $message,
            group: $group,
            used: $used,
            quota: $quota,
            denialReason: $denialReason,
        );
    }
}
