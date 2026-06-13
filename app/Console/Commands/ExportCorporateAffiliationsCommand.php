<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExportCorporateAffiliations;
use App\Support\Exports\CorporateAffiliationsExportService;
use Illuminate\Console\Command;

class ExportCorporateAffiliationsCommand extends Command
{
    protected $signature = 'export:corporate-affiliations
                            {--sync : Ejecuta la exportación de inmediato, sin encolar en la cola system}';

    protected $description = 'Genera Excel .xlsx de afiliaciones corporativas con planes y afiliados, y notifica por WhatsApp';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Ejecutando exportación de afiliaciones corporativas de forma síncrona…');

            (new ExportCorporateAffiliations)->handle(app(CorporateAffiliationsExportService::class));

            $this->info('Exportación finalizada. Revise el resumen enviado por WhatsApp.');

            return self::SUCCESS;
        }

        ExportCorporateAffiliations::dispatch()->onQueue('system');

        $this->info('Job encolado en la cola "system". Asegúrate de tener un worker activo:');
        $this->line('  php artisan queue:work --queue=system --once');

        return self::SUCCESS;
    }
}
