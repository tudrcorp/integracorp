<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackupDatabase;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
                            {--sync : Ejecuta el respaldo de inmediato, sin encolar en la cola system}';

    protected $description = 'Genera un respaldo .sql completo de la base de datos y notifica por WhatsApp';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Ejecutando respaldo de base de datos de forma síncrona…');

            (new BackupDatabase)->handle(app(\App\Support\Database\DatabaseBackupService::class));

            $this->info('Respaldo finalizado. Revise el resumen enviado por WhatsApp.');

            return self::SUCCESS;
        }

        BackupDatabase::dispatch()->onQueue('system');

        $this->info('Job de respaldo encolado en la cola "system". Asegúrate de tener un worker activo:');
        $this->line('  php artisan queue:work --queue=system --once');

        return self::SUCCESS;
    }
}
