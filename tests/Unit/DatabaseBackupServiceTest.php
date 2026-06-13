<?php

declare(strict_types=1);

use App\Support\Database\DatabaseBackupService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

it('genera un respaldo sqlite con estructura y datos', function (): void {
    if (! file_exists('/usr/bin/sqlite3') && ! file_exists('/opt/homebrew/bin/sqlite3')) {
        $this->markTestSkipped('sqlite3 no está disponible en este entorno.');
    }

    $databasePath = storage_path('framework/testing/backup-source.sqlite');

    File::ensureDirectoryExists(dirname($databasePath));
    File::delete($databasePath);
    touch($databasePath);

    Config::set('database.connections.backup_test_sqlite', [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Config::set('database.default', 'backup_test_sqlite');
    Config::set('backup.directory', 'database-backups-test');

    DB::connection('backup_test_sqlite')->statement('CREATE TABLE backup_probe (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    DB::connection('backup_test_sqlite')->table('backup_probe')->insert(['name' => 'integracorp']);

    $service = app(DatabaseBackupService::class);
    $result = $service->create('backup_test_sqlite');

    expect($result->filename)->toEndWith('.sql')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and($result->driver)->toBe('sqlite')
        ->and(Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    $sql = Storage::disk('public')->get($result->publicRelativePath);

    expect($sql)
        ->toContain('backup_probe')
        ->toContain('integracorp');

    Storage::disk('public')->deleteDirectory('database-backups-test');
    File::delete($databasePath);
});

it('rechaza respaldos sqlite en memoria', function (): void {
    Config::set('database.connections.backup_memory', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    expect(fn () => app(DatabaseBackupService::class)->create('backup_memory'))
        ->toThrow(RuntimeException::class, 'No se puede respaldar SQLite en memoria');
});

it('elimina respaldos expirados del disco publico', function (): void {
    Config::set('backup.directory', 'database-backups-test');
    Config::set('backup.retention_days', 7);

    Storage::disk('public')->makeDirectory('database-backups-test');
    Storage::disk('public')->put('database-backups-test/old-backup.sql', '-- old');
    touch(Storage::disk('public')->path('database-backups-test/old-backup.sql'), now()->subDays(10)->getTimestamp());

    Storage::disk('public')->put('database-backups-test/new-backup.sql', '-- new');

    $deleted = app(DatabaseBackupService::class)->purgeExpiredBackups();

    expect($deleted)->toBe(1)
        ->and(Storage::disk('public')->exists('database-backups-test/old-backup.sql'))->toBeFalse()
        ->and(Storage::disk('public')->exists('database-backups-test/new-backup.sql'))->toBeTrue();

    Storage::disk('public')->deleteDirectory('database-backups-test');
});
