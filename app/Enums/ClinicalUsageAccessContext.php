<?php

declare(strict_types=1);

namespace App\Enums;

enum ClinicalUsageAccessContext: string
{
    case PlanUsage = 'plan_usage';
    case PlanCreate = 'plan_create';
    case PlanEdit = 'plan_edit';
    case BenefitCreate = 'benefit_create';
    case BenefitEdit = 'benefit_edit';

    public function label(): string
    {
        return match ($this) {
            self::PlanUsage => 'Uso clínico del plan',
            self::PlanCreate => 'Creación de plan (uso clínico)',
            self::PlanEdit => 'Edición de plan (uso clínico)',
            self::BenefitCreate => 'Creación de beneficio (uso clínico)',
            self::BenefitEdit => 'Edición de beneficio (uso clínico)',
        };
    }
}
