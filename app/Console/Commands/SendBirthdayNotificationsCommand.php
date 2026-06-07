<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendNotificationBirthday;
use Illuminate\Console\Command;

class SendBirthdayNotificationsCommand extends Command
{
    protected $signature = 'birthday:send-notifications
                            {--sync : Ejecuta el job de inmediato, sin encolar en la cola system}';

    protected $description = 'Envía las tarjetas de cumpleaños (misma lógica que el schedule diario de las 8:00)';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Ejecutando envío de cumpleaños de forma síncrona…');

            (new SendNotificationBirthday)->handle();

            $this->info('Proceso finalizado.');

            return self::SUCCESS;
        }

        SendNotificationBirthday::dispatch()->onQueue('system');

        $this->info('Job encolado en la cola "system". Asegúrate de tener un worker activo:');
        $this->line('  php artisan queue:work --queue=system --once');

        return self::SUCCESS;
    }
}
