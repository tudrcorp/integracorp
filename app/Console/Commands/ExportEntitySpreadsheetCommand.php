<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExportScheduledEntity;
use App\Support\Exports\AbstractScheduledEntityExportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ExportEntitySpreadsheetCommand extends Command
{
    protected $signature = 'export:entity
                            {type : agents|agencies|natural_providers|juridical_providers|collaborators|doctors}
                            {--sync : Ejecuta la exportación de inmediato, sin encolar en la cola system}';

    protected $description = 'Genera Excel .xlsx de entidades maestras (agentes, agencias, proveedores, etc.) y notifica por WhatsApp';

    public function handle(): int
    {
        $exportKey = (string) $this->argument('type');
        $meta = config('scheduled-exports.exports.'.$exportKey);

        if (! is_array($meta)) {
            $this->error("Tipo de exportación desconocido: {$exportKey}");

            return self::FAILURE;
        }

        $serviceClass = $meta['service'] ?? null;

        if (! is_string($serviceClass) || ! is_subclass_of($serviceClass, AbstractScheduledEntityExportService::class)) {
            throw new InvalidArgumentException("Servicio de exportación inválido para: {$exportKey}");
        }

        if ($this->option('sync')) {
            $this->info('Ejecutando exportación «'.($meta['title'] ?? $exportKey).'» de forma síncrona…');

            (new ExportScheduledEntity($exportKey))->handle();

            $this->info('Exportación finalizada. Revise el resumen enviado por WhatsApp.');

            return self::SUCCESS;
        }

        ExportScheduledEntity::dispatch($exportKey)->onQueue('system');

        $this->info('Job encolado en la cola "system". Asegúrate de tener un worker activo:');
        $this->line('  php artisan queue:work --queue=system --once');

        return self::SUCCESS;
    }
}
