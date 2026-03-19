<?php

namespace App\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use SplTempFileObject;
use Throwable;

class SupplierExportCsv extends ExportCsv
{
    /**
     * {@inheritdoc}
     *
     * Registra en el log el ID del proveedor cuando una fila falla para poder localizar registros problemáticos.
     */
    public function handle(): void
    {
        $user = $this->export->user;
        auth()->setUser($user);

        $processedRows = 0;
        $successfulRows = 0;

        $csv = Writer::from(new SplTempFileObject);
        $csv->setDelimiter($this->exporter::getCsvDelimiter());

        $query = EloquentSerializeFacade::unserialize($this->query);

        foreach ($this->exporter->getCachedColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        foreach ($query->find($this->records) as $record) {
            try {
                $csv->insertOne(($this->exporter)($record));
                $successfulRows++;
            } catch (Throwable $exception) {
                Log::warning('SupplierExport: fila fallida', [
                    'export_id' => $this->export->getKey(),
                    'page' => $this->page,
                    'supplier_id' => $record->getKey(),
                    'message' => $exception->getMessage(),
                ]);
                report($exception);
            }
            $processedRows++;
        }

        $filePath = $this->export->getFileDirectory().DIRECTORY_SEPARATOR.str_pad(strval($this->page), 16, '0', STR_PAD_LEFT).'.csv';

        DB::transaction(function () use ($csv, $filePath, $processedRows, $successfulRows): void {
            $this->export::query()
                ->whereKey($this->export->getKey())
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('processed_rows + '.$processedRows),
                    'successful_rows' => new Expression('successful_rows + '.$successfulRows),
                ]);

            $this->export::query()
                ->whereKey($this->export->getKey())
                ->whereColumn('processed_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('total_rows'),
                ]);

            $this->export::query()
                ->whereKey($this->export->getKey())
                ->whereColumn('successful_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'successful_rows' => new Expression('total_rows'),
                ]);

            $this->export->getFileDisk()->put($filePath, $csv->toString(), Filesystem::VISIBILITY_PRIVATE);
        });
    }
}
