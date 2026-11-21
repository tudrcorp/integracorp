<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canal por el que sale un mensaje del sistema.
 */
enum OutboundChannel: string
{
    case WhatsApp = 'whatsapp';
    case Mail = 'mail';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Mail => 'Correo',
            self::Telegram => 'Telegram',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WhatsApp => 'heroicon-m-chat-bubble-left-right',
            self::Mail => 'heroicon-m-envelope',
            self::Telegram => 'heroicon-m-paper-airplane',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WhatsApp => 'success',
            self::Mail => 'warning',
            self::Telegram => 'info',
        };
    }
}
