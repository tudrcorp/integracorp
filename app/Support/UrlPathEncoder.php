<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Codifica una ruta relativa de archivo para usarla dentro de una URL.
 *
 * Cada segmento se codifica por separado para conservar las barras: un
 * archivo como `telemedicina-doc/V-1-REF-1-Informe-Médico.pdf` viaja como
 * `telemedicina-doc/V-1-REF-1-Informe-M%C3%A9dico.pdf`. Los nombres ASCII
 * habituales (letras, dígitos, `-`, `_`, `.`) quedan idénticos.
 */
final class UrlPathEncoder
{
    public static function encode(string $relativePath): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $relativePath),
        ));
    }
}
