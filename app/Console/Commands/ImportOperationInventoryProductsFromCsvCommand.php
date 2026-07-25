<?php

namespace App\Console\Commands;

use App\Services\OperationInventoryProductCsvImporter;
use Illuminate\Console\Command;

class ImportOperationInventoryProductsFromCsvCommand extends Command
{
    protected $signature = 'operation-inventory-products:import-csv
                            {path? : Ruta al CSV (por defecto database/data/operation_inventory_products.csv)}';

    protected $description = 'Importa productos de inventario Diagnomovil desde un CSV';

    public function handle(OperationInventoryProductCsvImporter $importer): int
    {
        $path = $this->argument('path') ?: database_path('data/operation_inventory_products.csv');

        if (! is_string($path) || $path === '') {
            $this->error('Debe indicar una ruta válida al CSV.');

            return self::FAILURE;
        }

        $this->info('Importando productos desde: '.$path);

        try {
            $result = $importer->importFromPath($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Creados: {$result['imported']} | Actualizados: {$result['updated']} | Omitidos: {$result['skipped']}");

        if ($result['duplicate_codes'] !== []) {
            $this->warn('Códigos duplicados en el CSV (se ajustaron con sufijo):');
            foreach ($result['duplicate_codes'] as $duplicate) {
                $this->line(' - '.$duplicate);
            }
        }

        return self::SUCCESS;
    }
}
