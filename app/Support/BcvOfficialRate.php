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
}
