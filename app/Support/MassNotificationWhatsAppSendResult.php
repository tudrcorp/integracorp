<?php

declare(strict_types=1);

namespace App\Support;

final class MassNotificationWhatsAppSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?string $phone = null,
    ) {}

    public static function ok(string $phone): self
    {
        return new self(success: true, phone: $phone);
    }

    public static function fail(string $errorMessage, ?string $phone = null): self
    {
        return new self(success: false, errorMessage: $errorMessage, phone: $phone);
    }
}
