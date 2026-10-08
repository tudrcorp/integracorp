<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\UserActivity\UserActivityRetention;
use Illuminate\Console\Command;

/**
 * Aplica la retención de Actividad de usuarios: 90 días de recorrido detallado
 * y 12 meses de resumen diario.
 */
class PurgeUserActivityCommand extends Command
{
    protected $signature = 'user-activity:purge';

    protected $description = 'Borra el recorrido de más de 90 días y los resúmenes de más de 12 meses de Actividad de usuarios.';

    public function handle(): int
    {
        $result = UserActivityRetention::purge();

        $this->info("Eventos borrados: {$result['events']}. Barras vaciadas: {$result['bars']}. Resúmenes borrados: {$result['days']}.");

        return self::SUCCESS;
    }
}
