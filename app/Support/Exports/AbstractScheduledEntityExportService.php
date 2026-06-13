<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Support\Exports\Concerns\SpreadsheetExportHelpers;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

abstract class AbstractScheduledEntityExportService
{
    use SpreadsheetExportHelpers;

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     *
     * @throws RuntimeException
     */
    abstract protected function populateSpreadsheet(Writer $writer): array;

    /**
     * @return list<string>
     */
    abstract public static function headers(): array;

    abstract protected function exportConfigKey(): string;

    abstract protected function defaultFilenamePrefix(): string;

    /**
     * @throws RuntimeException
     */
    public function create(): ScheduledExportResult
    {
        $startedAt = microtime(true);
        $exportConfig = config('scheduled-exports.exports.'.$this->exportConfigKey(), []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? $this->exportConfigKey());
        $directory = trim($baseDirectory.'/'.$subDirectory, '/');
        $filename = $this->buildFilename((string) ($exportConfig['filename_prefix'] ?? $this->defaultFilenamePrefix()));
        $temporaryPath = storage_path('app/'.$directory.'/tmp/'.$filename);

        File::ensureDirectoryExists(dirname($temporaryPath));

        $writer = new Writer;
        $writer->openToFile($temporaryPath);
        $writer->addRow(Row::fromValues(static::headers()));

        $counts = $this->populateSpreadsheet($writer);

        $writer->close();

        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            throw new RuntimeException('El archivo Excel no se generó o quedó vacío.');
        }

        $bytes = (int) filesize($temporaryPath);
        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put($directory.'/'.$filename, file_get_contents($temporaryPath) ?: '');

        $publicRelativePath = $directory.'/'.$filename;
        $absolutePath = Storage::disk('public')->path($publicRelativePath);

        File::delete($temporaryPath);

        return new ScheduledExportResult(
            filename: $filename,
            absolutePath: $absolutePath,
            publicRelativePath: $publicRelativePath,
            bytes: $bytes,
            durationSeconds: microtime(true) - $startedAt,
            affiliationCount: $counts['recordCount'],
            affiliateCount: $counts['noteCount'],
            rowCount: $counts['rowCount'],
        );
    }

    public function purgeExpiredExports(): int
    {
        $exportConfig = config('scheduled-exports.exports.'.$this->exportConfigKey(), []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? $this->exportConfigKey());
        $directory = trim($baseDirectory.'/'.$subDirectory, '/');
        $retentionDays = max(1, (int) config('scheduled-exports.retention_days', 7));
        $threshold = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        if (! Storage::disk('public')->exists($directory)) {
            return 0;
        }

        foreach (Storage::disk('public')->files($directory) as $file) {
            if (! str_ends_with(strtolower($file), '.xlsx')) {
                continue;
            }

            if (Storage::disk('public')->lastModified($file) >= $threshold) {
                continue;
            }

            Storage::disk('public')->delete($file);
            $deleted++;
        }

        return $deleted;
    }
}
