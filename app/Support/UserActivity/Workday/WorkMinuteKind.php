<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

/**
 * Lo que significa un minuto del día frente a la jornada. Es lo que pinta la
 * barra «Su día»: colores vivos y distintos para leerla de un vistazo.
 */
enum WorkMinuteKind: string
{
    case WorkActive = 'W';
    case WorkIdle = 'I';
    case WorkAbsent = 'X';
    case Lunch = 'L';
    case LunchExcess = 'E';
    case GuardActive = 'G';
    case GuardIdle = 'H';
    case GuardAbsent = 'A';
    case Overtime = 'O';
    case OffHoursIdle = 'B';
    case Free = '.';

    public function label(): string
    {
        return match ($this) {
            self::WorkActive => 'Activo en jornada',
            self::WorkIdle => 'Inactivo en jornada',
            self::WorkAbsent => 'Ausente en jornada',
            self::Lunch => 'Almuerzo',
            self::LunchExcess => 'Exceso de almuerzo',
            self::GuardActive => 'Activo en guardia',
            self::GuardIdle => 'Conectado en guardia',
            self::GuardAbsent => 'Sin conexión en guardia',
            self::Overtime => 'Horas extra',
            self::OffHoursIdle => 'Abierto fuera de jornada',
            self::Free => 'Libre',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WorkActive => 'En su horario y usando el sistema.',
            self::WorkIdle => 'En su horario, con el sistema abierto pero sin tocarlo o en otra pestaña.',
            self::WorkAbsent => 'En su horario y sin el sistema abierto, sin haber declarado almuerzo.',
            self::Lunch => 'Hora de almuerzo declarada (hasta 60 min).',
            self::LunchExcess => 'Pasó de los 60 minutos de almuerzo.',
            self::GuardActive => 'De guardia y usando el sistema.',
            self::GuardIdle => 'De guardia, conectado sin interactuar. No se penaliza.',
            self::GuardAbsent => 'De guardia y sin el sistema abierto.',
            self::Overtime => 'Trabajó fuera de su horario, en fin de semana o feriado.',
            self::OffHoursIdle => 'Fuera de su horario, con el sistema abierto sin usarlo. No cuenta como extra.',
            self::Free => 'Fuera de jornada y sin actividad.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WorkActive => '#16a34a',
            self::WorkIdle => '#facc15',
            self::WorkAbsent => '#ef4444',
            self::Lunch => '#3b82f6',
            self::LunchExcess => '#f97316',
            self::GuardActive => '#4f46e5',
            self::GuardIdle => '#a5b4fc',
            self::GuardAbsent => '#be123c',
            self::Overtime => '#d946ef',
            self::OffHoursIdle => '#cbd5e1',
            self::Free => 'transparent',
        };
    }

    public function isGuard(): bool
    {
        return in_array($this, [self::GuardActive, self::GuardIdle, self::GuardAbsent], true);
    }

    /**
     * Orden de la leyenda.
     *
     * @return list<self>
     */
    public static function legend(): array
    {
        return [
            self::WorkActive, self::WorkIdle, self::WorkAbsent, self::Lunch, self::LunchExcess,
            self::Overtime, self::GuardActive, self::GuardIdle, self::GuardAbsent, self::OffHoursIdle,
        ];
    }
}
