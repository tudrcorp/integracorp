<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Str;

/**
 * Servicios derivados que deben resaltarse como críticos en UI (tablas, infolists).
 */
final class TelemedicineDerivedServiceBadge
{
    public static function driftNameIsCritical(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $normalized = strtoupper(Str::ascii(trim($name)));

        return str_contains($normalized, 'TRASLADO EN AMBULANCIA')
            || str_contains($normalized, 'INGRESO A CLINICA');
    }

    /**
     * Solo «TRASLADO EN AMBULANCIA» normalizado (sin depender de derivados tipo ingreso).
     */
    public static function specificServiceIsTrasladoEnAmbulancia(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $collapsed = preg_replace('/\s+/', ' ', trim($name));
        $normalized = strtoupper(Str::ascii((string) $collapsed));

        return $normalized === 'TRASLADO EN AMBULANCIA';
    }
}
