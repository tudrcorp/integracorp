<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExportIndividualAffiliations;
use App\Support\Exports\IndividualAffiliationsExportService;
use Illuminate\Console\Command;

class ExportIndividualAffiliationsCommand extends Command
{
    protected $signature = 'export:individual-affiliations
                            {--sync : Ejecuta la exportación de inmediato, sin encolar en la cola system}';

    protected $description = 'Genera Excel .xlsx de afiliaciones individuales con afiliados y notifica por WhatsApp';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Ejecutando exportación de afiliaciones individuales de forma síncrona…');

            (new ExportIndividualAffiliations)->handle(app(IndividualAffiliationsExportService::class));

            $this->info('Exportación finalizada. Revise el resumen enviado por WhatsApp.');

            return self::SUCCESS;
        }

        ExportIndividualAffiliations::dispatch()->onQueue('system');

        $this->info('Job encolado en la cola "system". Asegúrate de tener un worker activo:');
        $this->line('  php artisan queue:work --queue=system --once');

        return self::SUCCESS;
    }
}
