<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Genera el token de la pantalla grande del monitor en vivo. No escribe el
 * .env: muestra la línea para pegarla, así el cambio queda a la vista.
 */
class GenerateLiveMonitorTvTokenCommand extends Command
{
    protected $signature = 'live-presence:tv-token';

    protected $description = 'Genera un token nuevo para la pantalla de TV del monitor en vivo';

    public function handle(): int
    {
        $token = Str::random(64);

        $this->info('Agregue (o reemplace) esta línea en el .env:');
        $this->line('');
        $this->line('LIVE_MONITOR_TV_TOKEN='.$token);
        $this->line('');
        $this->info('Luego ejecute: php artisan config:cache');
        $this->info('Enlace de la pantalla: '.rtrim((string) config('app.url'), '/').'/monitor/tv/'.$token);
        $this->warn('Cualquiera con el enlace ve el monitor. Si se filtra, genere otro token: el anterior deja de funcionar de inmediato.');

        return self::SUCCESS;
    }
}
