<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Presión arterial legible: «sistólica/diastólica mmHg».
 *
 * El campo de la consulta es numérico y la columna `decimal(8,2)`, así que el
 * médico no puede escribir «110/70» y la escribe como «110.70». La base además
 * puede devolverla como «110.7» (cero final perdido). Aquí se reconoce ese
 * patrón y se muestra «110/70 mmHg». Lo que no parezca una presión se deja tal cual.
 */
final class TelemedicineBloodPressure
{
    public const EMPTY = '—';

    public static function format(mixed $value): string
    {
        $raw = is_scalar($value) ? trim((string) $value) : '';

        if ($raw === '') {
            return self::EMPTY;
        }

        if (preg_match('/^(\d{2,3})\s*([\/.,\-\s])\s*(\d{1,3})\s*(mm\s*hg)?$/i', $raw, $match) !== 1) {
            return $raw;
        }

        $systolic = (int) $match[1];
        $diastolicDigits = $match[3];

        /** «110.7» viene de un decimal: el cero final se perdió, es 110/70. */
        if (in_array($match[2], ['.', ','], true) && strlen($diastolicDigits) === 1) {
            $diastolicDigits .= '0';
        }

        $diastolic = (int) $diastolicDigits;

        if ($systolic < 50 || $systolic > 300 || $diastolic < 20 || $diastolic > 200 || $diastolic >= $systolic) {
            return $raw;
        }

        return $systolic.'/'.$diastolic.' mmHg';
    }
}
