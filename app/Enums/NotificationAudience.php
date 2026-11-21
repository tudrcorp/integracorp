<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A quién va un mensaje: al personal de TDG o fuera de TDG (agentes, agencias,
 * afiliados, pacientes, proveedores, empresas).
 */
enum NotificationAudience: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interna',
            self::External => 'Externa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Internal => 'heroicon-m-building-office-2',
            self::External => 'heroicon-m-globe-alt',
        };
    }
}
