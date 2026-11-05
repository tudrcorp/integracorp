<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\ApiBcvController;

final class BcvOfficialRate
{
    /**
     * Tasa oficial BCV (USD/VES) desde la API pública. Una sola petición por request.
     */
    public static function resolve(): ?float
    {
        static $resolved = false;
        static $rate = null;

        if (! $resolved) {
            $resolved = true;
            $fetched = ApiBcvController::getTasaBcv();
            $rate = is_numeric($fetched) ? (float) $fetched : null;
        }

        return $rate;
    }

    /**
     * Tasa sugerida para formularios: la oficial, guardada una hora para no consultar la API en
     * cada apertura de una pantalla. Si la API no responde, null (el campo queda editable).
     */
    public static function suggested(): ?float
    {
        try {
            $cached = \Illuminate\Support\Facades\Cache::get('bcv-official-rate:suggested');

            if (is_numeric($cached) && (float) $cached > 0) {
                return (float) $cached;
            }

            $rate = self::resolve();

            if ($rate !== null && $rate > 0) {
                \Illuminate\Support\Facades\Cache::put('bcv-official-rate:suggested', $rate, now()->addHour());
            }

            return $rate;
        } catch (\Throwable) {
            return null;
        }
    }
}
