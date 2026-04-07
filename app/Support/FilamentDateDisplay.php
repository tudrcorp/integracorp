<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class FilamentDateDisplay
{
    /**
     * Formatea fechas para columnas Filament cuando el valor puede ser Carbon,
     * fecha ISO (MySQL) o cadena guardada como d/m/Y.
     */
    public static function toDmy(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if ($state instanceof CarbonInterface) {
            return $state->format('d/m/Y');
        }

        if (is_string($state)) {
            $trimmed = trim($state);
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
                return $trimmed;
            }
            try {
                return Carbon::parse($trimmed)->format('d/m/Y');
            } catch (\Throwable) {
                return $trimmed;
            }
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }
}
