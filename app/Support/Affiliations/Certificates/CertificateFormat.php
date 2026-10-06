<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use Illuminate\Support\Str;

/**
 * Formatos de texto del certificado: nombres, documentos y parentescos.
 */
final class CertificateFormat
{
    /**
     * «GLADIS  GUILLEN » → «Gladis Guillen». Vacío o marcadores («...», «-») → «—».
     */
    public static function name(?string $value): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        $clean = trim($clean, ' .-');

        if ($clean === '') {
            return '—';
        }

        return mb_convert_case(mb_strtolower($clean), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * «24440387» → «C.I. V-24.440.387»; «J-40123456-7» → «RIF J-40123456-7».
     * Lo que ya trae otro formato se respeta tal cual.
     */
    public static function document(?string $value): string
    {
        $raw = Str::upper(trim((string) $value));

        if ($raw === '') {
            return '—';
        }

        if (preg_match('/^(?:([VE])[-\s.]?)?(\d{5,10})$/', str_replace('.', '', $raw), $matches) === 1) {
            $letter = $matches[1] !== '' ? $matches[1] : 'V';

            return 'C.I. '.$letter.'-'.number_format((int) $matches[2], 0, ',', '.');
        }

        if (preg_match('/^[JGP]/', $raw) === 1) {
            return 'RIF '.$raw;
        }

        return $raw;
    }

    /**
     * Versión de una línea para tablas de alto fijo (la relación de afiliados):
     * el nombre completo sigue en el carnet.
     */
    public static function short(string $value, int $width = 42): string
    {
        return mb_strimwidth($value, 0, $width, '…', 'UTF-8');
    }

    public static function relationship(?string $value): string
    {
        $clean = trim((string) $value);

        return $clean === '' ? 'Titular' : Str::ucfirst(mb_strtolower($clean));
    }
}
