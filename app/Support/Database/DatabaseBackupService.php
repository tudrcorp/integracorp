<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DatabaseBackupService
{
    /**
     * @throws RuntimeException
     */
    public function create(?string $connectionName = null): DatabaseBackupResult
    {
        $startedAt = microtime(true);
        $connectionName ??= (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            throw new RuntimeException("Conexión de base de datos no configurada: {$connectionName}.");
        }

        $driver = (string) ($connection['driver'] ?? '');
        $database = (string) ($connection['database'] ?? '');
        $filename = $this->buildFilename($database);
        $directory = (string) config('backup.directory', 'database-backups');
        $temporaryPath = storage_path('app/'.$directory.'/tmp/'.$filename);

        File::ensureDirectoryExists(dirname($temporaryPath));

        match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysqlCompatible($connection, $temporaryPath),
            'sqlite' => $this->dumpSqlite($connection, $temporaryPath),
            default => throw new RuntimeException("Motor de base de datos no soportado para respaldo: {$driver}."),
        };

        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            throw new RuntimeException('El archivo de respaldo no se generó o quedó vacío.');
        }

        $bytes = (int) filesize($temporaryPath);
        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put($directory.'/'.$filename, file_get_contents($temporaryPath) ?: '');

        $publicRelativePath = $directory.'/'.$filename;
        $absolutePath = Storage::disk('public')->path($publicRelativePath);

        File::delete($temporaryPath);

        return new DatabaseBackupResult(
            filename: $filename,
            absolutePath: $absolutePath,
            publicRelativePath: $publicRelativePath,
            bytes: $bytes,
            driver: $driver,
            database: $database,
            durationSeconds: microtime(true) - $startedAt,
        );
    }

    public function purgeExpiredBackups(): int
    {
        $directory = (string) config('backup.directory', 'database-backups');
        $retentionDays = max(1, (int) config('backup.retention_days', 7));
        $threshold = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        if (! Storage::disk('public')->exists($directory)) {
            return 0;
        }

        foreach (Storage::disk('public')->files($directory) as $file) {
            if (! str_ends_with(strtolower($file), '.sql')) {
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

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dumpMysqlCompatible(array $connection, string $outputPath): void
    {
        $binary = ($connection['driver'] ?? '') === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
        $command = [
            $binary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--add-drop-table',
            '--complete-insert',
            '--result-file='.$outputPath,
            (string) ($connection['database'] ?? ''),
        ];

        if (filled($connection['unix_socket'] ?? null)) {
            $command = [
                $binary,
                '--socket='.(string) $connection['unix_socket'],
                '--user='.($connection['username'] ?? 'root'),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--add-drop-table',
                '--complete-insert',
                '--result-file='.$outputPath,
                (string) ($connection['database'] ?? ''),
            ];
        }

        $environment = [];

        if (filled($connection['password'] ?? null)) {
            $environment['MYSQL_PWD'] = (string) $connection['password'];
        }

        $result = Process::timeout(3600)
            ->env($environment)
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output() ?: 'Fallo al ejecutar '.$binary.'.'));
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dumpSqlite(array $connection, string $outputPath): void
    {
        $databasePath = (string) ($connection['database'] ?? '');

        if ($databasePath === ':memory:') {
            throw new RuntimeException('No se puede respaldar SQLite en memoria. Use una base de datos en archivo.');
        }

        if (! is_file($databasePath)) {
            throw new RuntimeException("Archivo SQLite no encontrado: {$databasePath}.");
        }

        $result = Process::timeout(3600)->run([
            'sqlite3',
            $databasePath,
            '.dump',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output() ?: 'Fallo al ejecutar sqlite3 .dump.'));
        }

        File::put($outputPath, $result->output());
    }

    private function buildFilename(string $database): string
    {
        $safeDatabase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $database) ?: 'database';

        return sprintf(
            'integracorp_%s_%s.sql',
            $safeDatabase,
            now()->format('Y-m-d_His'),
        );
    }
}
