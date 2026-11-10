<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué información lleva la ficha del afiliado. Los datos del afiliado y de su
 * afiliación van siempre; esto decide los bloques de Administración y de
 * Operaciones y telemedicina.
 */
enum AffiliateFichaScope: string
{
    case Administration = 'administracion';
    case Clinical = 'operaciones';
    case All = 'todo';

    public function label(): string
    {
        return match ($this) {
            self::Administration => 'Administración',
            self::Clinical => 'Operaciones y telemedicina',
            self::All => 'Toda la información',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administration => 'Tarifa, upgrades, pagador, avisos de cobro, pagos y renovaciones.',
            self::Clinical => 'Cupos del plan, casos, consultas, indicaciones, antecedentes y servicios coordinados.',
            self::All => 'Administración más operaciones y telemedicina en un solo documento.',
        };
    }

    public function includesAdministration(): bool
    {
        return $this !== self::Clinical;
    }

    /**
     * Lleva datos clínicos: exige el permiso {@see \App\Support\Filament\BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA}.
     */
    public function includesClinical(): bool
    {
        return $this !== self::Administration;
    }
}
