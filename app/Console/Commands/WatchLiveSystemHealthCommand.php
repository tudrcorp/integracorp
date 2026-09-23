<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LivePresence\SystemHealthWatcher;
use Illuminate\Console\Command;

/**
 * Vigilante de colas y errores. Lo ejecuta el scheduler cada minuto, fuera de
 * la cola: si los workers se caen, igual avisa.
 */
class WatchLiveSystemHealthCommand extends Command
{
    protected $signature = 'live-presence:watch';

    protected $description = 'Revisa colas, trabajos fallidos y errores, y avisa por WhatsApp y correo si algo necesita atención';

    public function handle(): int
    {
        $result = SystemHealthWatcher::run();

        $this->info('Problemas detectados: '.$result['detected'].' · avisos enviados: '.$result['sent'].' · en pausa: '.$result['silenced'].'.');

        return self::SUCCESS;
    }
}
