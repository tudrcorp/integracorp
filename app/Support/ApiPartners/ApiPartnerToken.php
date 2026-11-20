<?php

declare(strict_types=1);

namespace App\Support\ApiPartners;

use Illuminate\Support\Str;

/**
 * Token de acceso de un aliado: `icp_<prefijo>.<secreto>`.
 *
 * - El prefijo es público y único: sirve para encontrar al aliado sin recorrer la tabla.
 * - Solo se guarda el SHA-256 del token completo; el token en claro se muestra una
 *   única vez al generarlo y no se puede recuperar.
 * - El secreto tiene 48 caracteres aleatorios (≈285 bits): un hash rápido es seguro
 *   aquí porque no hay nada que adivinar por diccionario.
 */
final class ApiPartnerToken
{
    public const SCHEME = 'icp_';

    public const PREFIX_LENGTH = 12;

    public const SECRET_LENGTH = 48;

    /**
     * @return array{token: string, prefix: string, hash: string}
     */
    public static function generate(): array
    {
        $prefix = Str::lower(Str::random(self::PREFIX_LENGTH));
        $token = self::SCHEME.$prefix.'.'.Str::random(self::SECRET_LENGTH);

        return ['token' => $token, 'prefix' => $prefix, 'hash' => self::hash($token)];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Prefijo del token si tiene la forma esperada; null si no la tiene (se rechaza sin ir a la base).
     */
    public static function prefixOf(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $pattern = '/^'.preg_quote(self::SCHEME, '/').'([a-z0-9]{'.self::PREFIX_LENGTH.'})\.[A-Za-z0-9]{'.self::SECRET_LENGTH.'}$/';

        return preg_match($pattern, $token, $matches) === 1 ? $matches[1] : null;
    }

    public static function matches(string $token, ?string $storedHash): bool
    {
        if ($storedHash === null || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, self::hash($token));
    }

    /**
     * Lo que se muestra del token en pantallas internas: nunca el secreto.
     */
    public static function displayPrefix(?string $prefix): string
    {
        return $prefix !== null && $prefix !== '' ? self::SCHEME.$prefix.'.••••' : 'Sin token';
    }
}
