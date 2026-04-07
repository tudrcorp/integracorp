<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class AffiliateVaucherIlsRemainingDays
{
    /**
     * Días calendario desde la fecha base (por defecto hoy) hasta dateEnd.
     * Si la fecha fin ya pasó, devuelve 0.
     */
    public static function remainingDaysUntilEnd(mixed $dateEnd, ?CarbonInterface $today = null): ?int
    {
        $end = self::parseToStartOfDay($dateEnd);
        if ($end === null) {
            return null;
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        if ($end->lt($today)) {
            return 0;
        }

        return (int) $today->diffInDays($end);
    }

    public static function parseToStartOfDay(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
