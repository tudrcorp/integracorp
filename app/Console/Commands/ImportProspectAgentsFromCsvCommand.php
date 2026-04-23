<?php

namespace App\Console\Commands;

use App\Services\ProspectAgentCsvImporter;
use Illuminate\Console\Command;

class ImportProspectAgentsFromCsvCommand extends Command
{
    protected $signature = 'prospect-agents:import-csv
                            {path : Ruta absoluta al archivo CSV (encabezado con las columnas del prospecto)}';

    protected $description = 'Trunca prospect_agents (y tablas hijas) e importa registros desde un CSV';

    public function handle(ProspectAgentCsvImporter $importer): int
    {
        $path = $this->argument('path');
        if (! is_string($path) || $path === '') {
            $this->error('Debe indicar una ruta válida al CSV.');

            return self::FAILURE;
        }

        $this->info('Importando desde: '.$path);

        try {
            $count = $importer->importFromPath($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Importación finalizada: {$count} filas insertadas en prospect_agents.");

        return self::SUCCESS;
    }
}
