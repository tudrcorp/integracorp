<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Línea de mando o staff (asesoría lateral: Legal, Auditoría). Un puesto staff no
 * tiene subordinados.
 */
enum OrgPositionKind: string
{
    case Line = 'LINEA';
    case Staff = 'STAFF';

    public function label(): string
    {
        return match ($this) {
            self::Line => 'Línea de mando',
            self::Staff => 'Staff / asesoría',
        };
    }
}
