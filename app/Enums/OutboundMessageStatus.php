<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado de un mensaje en la bitácora del Centro de notificaciones.
 */
enum OutboundMessageStatus: string
{
    /** El proveedor (UltraMsg, servidor de correo, Telegram) lo aceptó. */
    case Sent = 'sent';

    /** El proveedor lo rechazó o no respondió. */
    case Failed = 'failed';

    /** No salió porque está apagado en el Centro de notificaciones. */
    case Blocked = 'blocked';

    /** Salió hacia el servidor de correo pero no llegó la confirmación. */
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Enviado',
            self::Failed => 'Falló',
            self::Blocked => 'Bloqueado',
            self::Pending => 'Sin confirmar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Blocked => 'gray',
            self::Pending => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sent => 'heroicon-m-check-circle',
            self::Failed => 'heroicon-m-x-circle',
            self::Blocked => 'heroicon-m-no-symbol',
            self::Pending => 'heroicon-m-clock',
        };
    }
}
