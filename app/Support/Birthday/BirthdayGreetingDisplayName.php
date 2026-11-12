<?php

declare(strict_types=1);

namespace App\Support\Birthday;

/**
 * Acorta el nombre completo para el saludo de la tarjeta animada
 * («Feliz cumpleaños, …») y evita solapes tipográficos.
 *
 * Convención venezolana habitual: uno o dos nombres + dos apellidos.
 * Se conserva el primer nombre y el primer apellido.
 */
final class BirthdayGreetingDisplayName
{
    public static function from(?string $fullName): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $fullName)) ?? '';

        if ($normalized === '') {
            return '';
        }

        $parts = array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $part): bool => $part !== '',
        ));

        $count = count($parts);

        if ($count === 1) {
            return $parts[0];
        }

        if ($count === 2) {
            return $parts[0].' '.$parts[1];
        }

        if ($count === 3) {
            return $parts[0].' '.$parts[1];
        }

        return $parts[0].' '.$parts[$count - 2];
    }
}
