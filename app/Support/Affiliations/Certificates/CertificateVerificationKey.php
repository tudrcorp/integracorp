<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

/**
 * Clave del certificado: `CER-XXXX-XXXX-XXXX-XXXX` (16 hexadecimales aleatorios).
 *
 * Es aleatoria y no derivada de datos: solo sirve para encontrar la emisión, y no
 * debe poder adivinarse a partir del código de la afiliación.
 */
final class CertificateVerificationKey
{
    public const PREFIX = 'CER';

    public static function generate(): string
    {
        return self::format(bin2hex(random_bytes(8)));
    }

    public static function format(string $hex): string
    {
        return self::PREFIX.'-'.implode('-', str_split(strtoupper($hex), 4));
    }

    /**
     * Acepta la clave con o sin guiones, en minúsculas o con espacios, y la deja
     * en su forma canónica. Null si no tiene la forma de una clave.
     */
    public static function normalize(?string $candidate): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $candidate) ?? '');

        if (str_starts_with($clean, self::PREFIX)) {
            $clean = substr($clean, strlen(self::PREFIX));
        }

        if (preg_match('/^[0-9A-F]{16}$/', $clean) !== 1) {
            return null;
        }

        return self::format($clean);
    }
}
