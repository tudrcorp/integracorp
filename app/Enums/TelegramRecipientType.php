<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramRecipientType: string
{
    /** Personal interno con usuario @tudrencasa.com; se vincula desde su panel. */
    case User = 'user';

    /** Contacto que no pertenece a @tudrencasa.com; lo agrega un SUPERADMIN con motivo. */
    case External = 'external';

    /** Grupo de Telegram donde se agregó el bot; necesita aprobación de un SUPERADMIN. */
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Personal',
            self::External => 'Contacto externo',
            self::Group => 'Grupo',
        };
    }

    public function isPerson(): bool
    {
        return $this !== self::Group;
    }
}
