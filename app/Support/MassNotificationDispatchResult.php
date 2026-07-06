<?php

declare(strict_types=1);

namespace App\Support;

final class MassNotificationDispatchResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $queuedJobs = 0,
    ) {}
}
