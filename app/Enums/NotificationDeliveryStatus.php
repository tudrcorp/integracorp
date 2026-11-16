<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    /** Registrado, esperando su turno en la cola. */
    case Pending = 'pending';

    /** Telegram (o el canal) confirmó la entrega. */
    case Sent = 'sent';

    /** Entregado a la cola de WhatsApp o de correo, que tiene sus propios reintentos. */
    case Queued = 'queued';

    /** Telegram no pudo entregarlo; salió por el respaldo (ver filas hijas). */
    case FellBack = 'fell_back';

    /** No se pudo entregar por ningún canal. */
    case Failed = 'failed';

    /** No se envió a propósito (destino desactivado, vencido o fuera del personal). */
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cola',
            self::Sent => 'Entregado',
            self::Queued => 'Enviado al respaldo',
            self::FellBack => 'Salió por respaldo',
            self::Failed => 'Falló',
            self::Skipped => 'Omitido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'success',
            self::Queued, self::FellBack => 'warning',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
