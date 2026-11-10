<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Para qué se emitió una clave OTP registrada en `system_otp_codes`.
 */
enum SystemOtpCodeKind: string
{
    case PopulationApply = 'POPULATION_APPLY';
    case PopulationRollback = 'POPULATION_ROLLBACK';
    case ClinicalLimitOverride = 'CLINICAL_LIMIT_OVERRIDE';
    case ClinicalUsageAccess = 'CLINICAL_USAGE_ACCESS';

    public function label(): string
    {
        return match ($this) {
            self::PopulationApply => 'Aplicar población corporativa',
            self::PopulationRollback => 'Revertir población corporativa',
            self::ClinicalLimitOverride => 'Servicio fuera de cupo (telemedicina)',
            self::ClinicalUsageAccess => 'Acceso a cupos clínicos',
        };
    }

    /**
     * Panel y pantalla donde el usuario escribe la clave.
     */
    public function whereLabel(): string
    {
        return match ($this) {
            self::PopulationApply, self::PopulationRollback => 'Negocios · Actualizar población',
            self::ClinicalLimitOverride => 'Telemedicina · Nueva consulta',
            self::ClinicalUsageAccess => 'Negocios · Planes y beneficios',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PopulationApply => 'success',
            self::PopulationRollback => 'warning',
            self::ClinicalLimitOverride => 'danger',
            self::ClinicalUsageAccess => 'info',
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
