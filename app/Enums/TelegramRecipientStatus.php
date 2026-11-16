<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramRecipientStatus: string
{
    /** Tiene enlace emitido pero todavía no pulsó «Iniciar» en el bot. */
    case PendingLink = 'pending_link';

    /** Grupo donde agregaron el bot y que un SUPERADMIN aún no aprueba. */
    case PendingApproval = 'pending_approval';

    /** Vinculado y habilitado para recibir avisos. */
    case Active = 'active';

    /** La persona bloqueó el bot o sacaron el bot del grupo: Telegram ya no entrega. */
    case Blocked = 'blocked';

    /** Desactivado a mano, desvinculado o vencido. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::PendingLink => 'Pendiente de vincular',
            self::PendingApproval => 'Pendiente de aprobación',
            self::Active => 'Activo',
            self::Blocked => 'Bloqueó el bot / bot retirado',
            self::Disabled => 'Desactivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingLink, self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Blocked => 'danger',
            self::Disabled => 'gray',
        };
    }
}
