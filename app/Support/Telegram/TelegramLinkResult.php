<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Models\TelegramRecipient;

/**
 * Resultado de pulsar «Iniciar» con un código de vinculación.
 */
final class TelegramLinkResult
{
    private function __construct(
        public readonly bool $linked,
        public readonly string $message,
        public readonly ?TelegramRecipient $recipient = null,
    ) {}

    public static function linked(TelegramRecipient $recipient, string $message): self
    {
        return new self(true, $message, $recipient);
    }

    public static function rejected(string $message): self
    {
        return new self(false, $message);
    }
}
