<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\UserActivity\UserActivityFlusher;
use App\Support\UserActivity\UserActivityTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cada minuto (scheduler, no cola): guarda en MySQL el recorrido y el resumen
 * del día de cada usuario. Corre aunque los workers de la cola estén caídos.
 */
class FlushUserActivityCommand extends Command
{
    protected $signature = 'user-activity:flush';

    protected $description = 'Guarda en la base la actividad de usuarios acumulada en Redis/caché (recorrido y resumen del día).';

    public function handle(): int
    {
        if (! UserActivityTracker::enabled()) {
            $this->line('Actividad de usuarios desactivada.');

            return self::SUCCESS;
        }

        try {
            $result = UserActivityFlusher::flush();
        } catch (Throwable $exception) {
            Log::warning('user-activity:flush no pudo volcar la actividad; se reintenta en el próximo minuto.', [
                'message' => $exception->getMessage(),
            ]);
            $this->error('No se pudo volcar la actividad: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Eventos guardados: {$result['events']}. Resúmenes actualizados: {$result['days']}.");

        return self::SUCCESS;
    }
}
