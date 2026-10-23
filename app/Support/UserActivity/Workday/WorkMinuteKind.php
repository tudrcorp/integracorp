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
    case Meeting = 'M';
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
            self::WorkActive => 'Activo en el sistema',
            self::Meeting => 'En reunión',
            self::WorkIdle => 'Inactivo – En otra pestaña',
            self::WorkAbsent => 'Ausente en jornada',
            self::Lunch => 'Almuerzo',
            self::LunchExcess => 'Exceso de almuerzo',
            self::GuardActive => 'Activo en guardia',
            self::GuardIdle => 'Conectado en guardia',
            self::GuardAbsent => 'Sin conexión en guardia',
            self::Overtime => 'Activo Plus',
            self::OffHoursIdle => 'Abierto fuera de jornada',
            self::Free => 'Libre',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WorkActive => 'En su horario y usando el sistema.',
            self::Meeting => 'Reunión declarada con su motivo. En la jornada cuenta como trabajo; fuera de ella, como horas extra.',
            self::WorkIdle => 'En su horario, con el sistema abierto pero sin tocarlo o en otra pestaña.',
            self::WorkAbsent => 'En su horario y sin el sistema abierto, sin haber declarado almuerzo.',
            self::Lunch => 'Hora de almuerzo declarada (hasta 60 min).',
            self::LunchExcess => 'Pasó de los 60 minutos de almuerzo.',
            self::GuardActive => 'De guardia y usando el sistema.',
            self::GuardIdle => 'De guardia, conectado sin interactuar. No se penaliza.',
            self::GuardAbsent => 'De guardia y sin el sistema abierto.',
            self::Overtime => 'Horas extra: trabajó fuera de su horario, en fin de semana o feriado.',
            self::OffHoursIdle => 'Fuera de su horario, con el sistema abierto sin usarlo. No cuenta como extra.',
            self::Free => 'Fuera de jornada y sin actividad.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WorkActive => '#16a34a',
            self::Meeting => '#0d9488',
            self::WorkIdle => '#facc15',
            self::WorkAbsent => '#ef4444',
            self::Lunch => '#3b82f6',
            self::LunchExcess => '#f59e0b',
            self::GuardActive => '#4f46e5',
            self::GuardIdle => '#a5b4fc',
            self::GuardAbsent => '#991b1b',
            self::Overtime => '#166534',
            self::OffHoursIdle => '#86efac',
            self::Free => 'transparent',
        };
    }

    /**
     * Qué efecto tiene el tramo en las cifras, para el tooltip de la barra:
     * texto y tono (`good` suma, `bad` resta, `extra` horas extra, `neutral` no cuenta).
     *
     * @return array{0: string, 1: string}
     */
    public function impact(): array
    {
        return match ($this) {
            self::WorkActive => ['Suma al uso de jornada', 'good'],
            self::Meeting => ['Suma al uso de jornada como trabajo', 'good'],
            self::WorkIdle => ['No suma: sistema abierto sin usarlo', 'neutral'],
            self::WorkAbsent => ['Baja el uso: ausencia en su horario', 'bad'],
            self::Lunch => ['No cuenta: hora de almuerzo declarada', 'neutral'],
            self::LunchExcess => ['Baja el uso: exceso de almuerzo', 'bad'],
            self::GuardActive => ['Presencia en guardia, usando el sistema', 'good'],
            self::GuardIdle => ['Presencia en guardia (no se penaliza)', 'good'],
            self::GuardAbsent => ['Sin presencia en su guardia', 'bad'],
            self::Overtime => ['Suma a horas extra', 'extra'],
            self::OffHoursIdle => ['No cuenta: fuera de horario y sin uso', 'neutral'],
            self::Free => ['', 'neutral'],
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
            self::WorkActive, self::Meeting, self::WorkIdle, self::WorkAbsent, self::Lunch, self::LunchExcess,
            self::Overtime, self::GuardActive, self::GuardIdle, self::GuardAbsent, self::OffHoursIdle,
        ];
    }
}
