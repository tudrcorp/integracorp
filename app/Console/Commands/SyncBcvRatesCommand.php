<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BcvRateHistory;
use Illuminate\Console\Command;

/**
 * Guarda el histórico de la tasa oficial BCV (ve.dolarapi.com) en `bcv_official_rates`.
 * Idempotente: solo agrega o corrige fechas. Corre a diario en el scheduler y se puede repetir a mano.
 */
class SyncBcvRatesCommand extends Command
{
    protected $signature = 'bcv:sync-rates';

    protected $description = 'Guarda el histórico de la tasa oficial BCV por fecha (para libros fiscales y conversiones)';

    public function handle(): int
    {
        $count = BcvRateHistory::sync();

        if ($count === null) {
            $this->warn('La API de tasas no respondió. Se mantienen las tasas ya guardadas (última: '.(BcvRateHistory::latestStored() ?? 'ninguna').').');

            return self::FAILURE;
        }

        $this->info($count.' fechas al día. Última tasa guardada: '.BcvRateHistory::latestStored().'.');

        return self::SUCCESS;
    }
}
