<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

/**
 * Ausencias justificadas: el día no tiene jornada que cumplir.
 */
enum WorkAbsenceType: string
{
    case Vacation = 'vacaciones';
    case MedicalLeave = 'reposo';
    case Permission = 'permiso';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacaciones',
            self::MedicalLeave => 'Reposo médico',
            self::Permission => 'Permiso',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Vacation => '🏖️',
            self::MedicalLeave => '🩺',
            self::Permission => '📝',
        };
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
