<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

/**
 * Número de control que INTEGRACORP envía al microservicio.
 *
 * El consecutivo ya existe en el código de la cotización (`COT-IND-0004011`,
 * `COT-CORP-0000123`): se le quita el prefijo y queda el formato de 7 dígitos
 * que espera el servicio. Nunca se deja que el servicio invente uno propio,
 * porque el documento debe poder rastrearse desde el portal.
 */
final class QuoteControlNumber
{
    private const LENGTH = 7;

    public static function fromCode(?string $code): string
    {
        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        if ($digits === '') {
            return str_repeat('0', self::LENGTH);
        }

        return str_pad(
            substr($digits, -self::LENGTH),
            self::LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }
}
