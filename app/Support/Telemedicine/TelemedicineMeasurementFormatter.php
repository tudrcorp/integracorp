<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Medidas clínicas para leer en español: coma decimal, sin ceros de relleno
 * y sin unidad cuando no hay dato.
 *
 * La base guarda el peso con tres decimales («71.200»), que en español se lee
 * como setenta y un mil doscientos. Aquí sale «71,2 kg»; sin dato, «—» a secas
 * (antes se imprimía «— kg»).
 */
final class TelemedicineMeasurementFormatter
{
    public const EMPTY = '—';

    public static function format(mixed $value, string $unit = '', int $maxDecimals = 2): string
    {
        $raw = is_scalar($value) ? trim((string) $value) : '';

        if ($raw === '') {
            return self::EMPTY;
        }

        /** Acepta «71.2» y «71,2»; si no es un número, se muestra tal cual. */
        $normalized = str_replace(',', '.', $raw);

        if (! is_numeric($normalized)) {
            return $raw;
        }

        $number = (float) $normalized;
        $formatted = number_format($number, $maxDecimals, ',', '.');

        if ($maxDecimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $unit === '' ? $formatted : $formatted.' '.$unit;
    }
}
