<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationDeliveryChannel: string
{
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Mail = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::WhatsApp => 'WhatsApp',
            self::Mail => 'Correo',
        };
    }
}
