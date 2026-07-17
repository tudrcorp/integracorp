<?php

namespace App\Support\Imports;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportActivityLogger
{
    public const CHANNEL = 'imports';

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        Log::channel(self::CHANNEL)->info($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        Log::channel(self::CHANNEL)->warning($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        Log::channel(self::CHANNEL)->error($message, $context);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $columnMap
     */
    public function logStarted(Import $import, array $columnMap, array $options): void
    {
        $this->info('Importación iniciada', [
            'import_id' => $import->getKey(),
            'importer' => $import->importer,
            'file_name' => $import->file_name,
            'total_rows' => $import->total_rows,
            'user_id' => $import->user_id,
            'options' => $options,
            'column_map' => $columnMap,
            'queue_connection' => config('queue.default'),
        ]);
    }

    public function logChunkProcessed(
        Import $import,
        int $chunkProcessedRows,
        int $chunkSuccessfulRows,
    ): void {
        $import->refresh();

        $chunkFailedRows = max(0, $chunkProcessedRows - $chunkSuccessfulRows);

        $this->info('Chunk de importación procesado', [
            'import_id' => $import->getKey(),
            'importer' => $import->importer,
            'chunk_processed_rows' => $chunkProcessedRows,
            'chunk_successful_rows' => $chunkSuccessfulRows,
            'chunk_failed_rows' => $chunkFailedRows,
            'processed_rows' => $import->processed_rows,
            'successful_rows' => $import->successful_rows,
            'total_rows' => $import->total_rows,
            'remaining_rows' => max(0, $import->total_rows - $import->processed_rows),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function logCompleted(Import $import, array $options): void
    {
        $import->refresh();

        $failedRowsCount = $import->getFailedRowsCount();
        $unprocessedRows = max(0, $import->total_rows - $import->processed_rows);
        $failedImportRowsStored = $import->failedRows()->count();
        $validationErrorSummary = $this->summarizeValidationErrors($import);

        $context = [
            'import_id' => $import->getKey(),
            'importer' => $import->importer,
            'file_name' => $import->file_name,
            'options' => $options,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows_count' => $failedRowsCount,
            'unprocessed_rows' => $unprocessedRows,
            'failed_import_rows_stored' => $failedImportRowsStored,
            'validation_error_summary' => $validationErrorSummary,
            'completed_at' => optional($import->completed_at)?->toDateTimeString(),
        ];

        if ($unprocessedRows > 0) {
            $context['likely_cause'] = ($unprocessedRows % 100 === 0)
                ? 'Chunks enteros no ejecutados: suele ser retryUntil corto, WithoutOverlapping o queue:work detenido/timeout.'
                : 'Jobs de cola incompletos o interrumpidos (revisar failed_jobs y queue:work).';

            $this->error('Importación incompleta: quedaron filas sin procesar (posible timeout/fallo de job en cola)', $context);

            return;
        }

        if ($failedRowsCount > 0) {
            $this->warning('Importación finalizada con filas fallidas', $context);

            return;
        }

        $this->info('Importación completada correctamente', $context);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     */
    public function logRowFailure(
        Import $import,
        array $row,
        string $reason,
        array $context = [],
    ): void {
        $this->warning('Fila de importación fallida', array_merge([
            'import_id' => $import->getKey(),
            'importer' => $import->importer,
            'reason' => $reason,
            'row_preview' => $this->previewRow($row),
        ], $context));
    }

    public function logException(Import $import, Throwable $exception, string $stage): void
    {
        $this->error('Excepción durante importación', [
            'import_id' => $import->getKey(),
            'importer' => $import->importer,
            'stage' => $stage,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function summarizeValidationErrors(Import $import): array
    {
        return $import->failedRows()
            ->whereNotNull('validation_error')
            ->pluck('validation_error')
            ->filter()
            ->countBy(fn (string $error): string => mb_substr($error, 0, 180))
            ->sortDesc()
            ->take(15)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function previewRow(array $row): array
    {
        $preview = [];

        foreach (array_slice($row, 0, 8, true) as $key => $value) {
            $preview[(string) $key] = is_string($value)
                ? mb_substr($value, 0, 80)
                : $value;
        }

        return $preview;
    }
}
