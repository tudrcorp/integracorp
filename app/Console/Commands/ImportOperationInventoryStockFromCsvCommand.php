<?php

namespace App\Console\Commands;

use App\Services\OperationInventoryStockCsvImporter;
use Illuminate\Console\Command;

class ImportOperationInventoryStockFromCsvCommand extends Command
{
    protected $signature = 'operation-inventory-stock:import-csv
                            {path? : Ruta al CSV de existencias por almacén}
                            {--no-truncate : No truncar inventarios/entradas/salidas/stocks antes de importar}';

    protected $description = 'Trunca inventarios e importa existencias por almacén desde CSV (ALMACEN 1=DIAGNOMOVIL, ALMACEN 2=3 DE FEBRERO)';

    public function handle(OperationInventoryStockCsvImporter $importer): int
    {
        $path = $this->argument('path') ?: database_path('data/operation_inventory_stock_by_warehouse.csv');

        if (! is_string($path) || $path === '') {
            $this->error('Debe indicar una ruta válida al CSV.');

            return self::FAILURE;
        }

        $truncate = ! (bool) $this->option('no-truncate');

        $this->warn($truncate
            ? 'Se truncarán operation_inventories, entries, outflows y product_stocks.'
            : 'Importación sin truncar tablas.');

        $this->info('Importando existencias desde: '.$path);

        try {
            $result = $importer->importFromPath($path, $truncate);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Inventarios: {$result['inventories']}");
        $this->info("Entradas: {$result['entries']}");
        $this->info("Salidas: {$result['outflows']}");
        $this->info("Stocks producto: {$result['stocks']}");
        $this->info("Omitidos: {$result['skipped']}");

        if ($result['missing_codes'] !== []) {
            $this->warn('Códigos sin producto: '.implode(', ', $result['missing_codes']));
        }

        return self::SUCCESS;
    }
}
