<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Destino por Telegram de un tipo de aviso del Centro de notificaciones.
 */
enum TelegramDeliveryMode: string
{
    /** No usa Telegram: el aviso sale como siempre (WhatsApp / correo). */
    case Off = 'off';

    /** A cada contacto del aviso que tenga Telegram vinculado. */
    case Person = 'person';

    /** A los grupos aprobados elegidos. */
    case Group = 'group';

    /** A las personas y además a los grupos. */
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Apagado · se envía como siempre',
            self::Person => 'Persona · a cada contacto vinculado',
            self::Group => 'Grupo · a los grupos elegidos',
            self::Both => 'Ambos · personas y grupos',
        };
    }

    public function includesPeople(): bool
    {
        return $this === self::Person || $this === self::Both;
    }

    public function includesGroups(): bool
    {
        return $this === self::Group || $this === self::Both;
    }
}
