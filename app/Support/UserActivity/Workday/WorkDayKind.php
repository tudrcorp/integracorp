<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

/**
 * Qué clase de día es para una persona. Se guarda en
 * `user_activity_days.workday_kind`; NULL es un día del régimen anterior.
 */
enum WorkDayKind: string
{
    case Workday = 'laboral';
    case Weekend = 'fin_semana';
    case Holiday = 'feriado';
    case Absence = 'ausencia';
    case GuardRest = 'descanso';
    case NoShift = 'sin_turno';
    case Exempt = 'exenta';
    case MedicalGuard = 'guardia_medica';

    public function label(): string
    {
        return match ($this) {
            self::Workday => 'Día laboral',
            self::Weekend => 'Fin de semana',
            self::Holiday => 'Feriado',
            self::Absence => 'Ausencia justificada',
            self::GuardRest => 'Descanso post-guardia',
            self::NoShift => 'Sin turno asignado',
            self::Exempt => 'Jornada exenta',
            self::MedicalGuard => 'Guardia médica',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Workday => '💼',
            self::Weekend => '🛋️',
            self::Holiday => '🎌',
            self::Absence => '📝',
            self::GuardRest => '😴',
            self::NoShift => '🗓️',
            self::Exempt => '⭐',
            self::MedicalGuard => '🩺',
        };
    }
}
