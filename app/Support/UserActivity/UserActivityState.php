<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

/**
 * Cómo está un usuario en un minuto dado. El código de una letra es el que se
 * guarda en Redis y en `user_activity_days.minute_states`.
 */
enum UserActivityState: string
{
    case Active = 'a';
    case Idle = 'i';
    case Background = 'b';
    case Offline = '.';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Idle => 'Inactivo',
            self::Background => 'En otra pestaña',
            self::Offline => 'Desconectado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'Está usando el sistema: tecleó, movió el mouse, tocó o desplazó la pantalla en los últimos minutos.',
            self::Idle => 'Tiene el sistema a la vista pero no lo toca desde hace rato.',
            self::Background => 'Tiene el sistema abierto en otra pestaña o ventana minimizada.',
            self::Offline => 'No tiene el sistema abierto.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => '#16a34a',
            self::Idle => '#f59e0b',
            self::Background => '#94a3b8',
            self::Offline => '#334155',
        };
    }

    /** Mayor gana cuando dos pestañas reportan estados distintos. */
    public function priority(): int
    {
        return match ($this) {
            self::Active => 3,
            self::Idle => 2,
            self::Background => 1,
            self::Offline => 0,
        };
    }

    public static function fromCode(?string $code): self
    {
        return self::tryFrom((string) $code) ?? self::Offline;
    }
}
