<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

/**
 * Cómo se arma la jornada de una persona. Se guarda en `users.work_schedule`;
 * NULL es la estándar.
 */
enum WorkScheduleType: string
{
    case Standard = 'estandar';
    case Guards = 'guardias';
    case Exempt = 'exenta';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Estándar',
            self::Guards => 'Por guardias',
            self::Exempt => 'Exenta',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Lunes a viernes de 8:00 a. m. a 5:00 p. m., con una hora de almuerzo declarada.',
            self::Guards => 'Su jornada son las guardias que tenga asignadas en el Calendario TDG o en Operaciones → Guardias.',
            self::Exempt => 'Se mide su actividad, pero no tiene jornada: sin base del 100 % ni ausencias.',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Standard;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
