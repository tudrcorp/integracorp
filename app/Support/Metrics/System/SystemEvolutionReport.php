<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Historial del trabajo hecho sobre el sistema, mes a mes: commits, líneas,
 * archivos, módulos, capas, migraciones y piezas nuevas.
 *
 * Se calcula una sola vez por commit desplegado (la clave de caché es el hash de
 * `HEAD`): mientras nadie suba código, la página no vuelve a leer git. Los
 * archivos generados o de terceros (vendor, public, locks, builds) no cuentan.
 */
final class SystemEvolutionReport
{
    private const CACHE_PREFIX = 'metrics:system:evolution:v2:';

    private const CACHE_SECONDS = 43200;

    private const TOP_COMMITS_PER_MONTH = 12;

    public function __construct(private readonly SystemGitHistory $git = new SystemGitHistory) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $head = $this->git->head();

        if ($head === null) {
            return self::unavailable();
        }

        $key = self::CACHE_PREFIX.$head;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            return Cache::lock(self::CACHE_PREFIX.'building', 180)->block(150, function () use ($key, $head): array {
                $cached = Cache::get($key);

                if (is_array($cached)) {
                    return $cached;
                }

                $payload = $this->build($head);
                Cache::put($key, $payload, self::CACHE_SECONDS);
                Cache::put(self::CACHE_PREFIX.'latest', $key, self::CACHE_SECONDS);

                return $payload;
            });
        } catch (Throwable $exception) {
            Log::warning('Métricas · Sistema: no se pudo calcular la evolución.', ['error' => $exception->getMessage()]);

            return self::unavailable();
        }
    }

    public static function flush(): void
    {
        $latest = Cache::get(self::CACHE_PREFIX.'latest');

        if (is_string($latest)) {
            Cache::forget($latest);
        }

        Cache::forget(self::CACHE_PREFIX.'latest');
        SystemDatabaseSnapshot::flush();
    }

    /**
     * @return array<string, mixed>
     */
    public static function unavailable(): array
    {
        return ['available' => false, 'months' => [], 'inventory' => [], 'total_lines' => 0, 'total_files' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $head): array
    {
        $commits = $this->git->commits();
        $tracked = $this->git->trackedFiles();

        return self::aggregate(
            $commits,
            $tracked,
            fn (string $path): ?string => $this->migrationContents($path),
        ) + ['head' => $head, 'generated_at' => CarbonImmutable::now()->toIso8601String()];
    }

    /**
     * Agrega los commits por mes. Sin efectos: se prueba con commits armados a mano.
     *
     * @param  list<array{hash: string, date: string, author: string, subject: string, files: list<array{path: string, added: int, deleted: int, binary: bool, status: string}>}>  $commits
     * @param  list<string>  $trackedFiles
     * @param  callable(string): ?string  $migrationContents
     * @return array<string, mixed>
     */
    public static function aggregate(array $commits, array $trackedFiles, callable $migrationContents): array
    {
        $months = [];
        $touched = [];
        $totalLines = 0;
        $lastCommitAt = null;

        usort($commits, static fn (array $left, array $right): int => strcmp($left['date'], $right['date']));

        foreach ($commits as $commit) {
            $month = substr($commit['date'], 0, 7);
            $day = (int) substr($commit['date'], 8, 2);

            if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1 || $day < 1) {
                continue;
            }

            $months[$month] ??= self::emptyMonth();
            $touched[$month] ??= [];
            $entry = &$months[$month];
            $entry['days'][$day] ??= self::emptyDay();

            $commitAdded = 0;
            $commitDeleted = 0;
            $commitModules = [];
            $commitFiles = 0;

            foreach ($commit['files'] as $file) {
                $path = $file['path'];

                if (SystemModuleCatalog::isGenerated($path)) {
                    continue;
                }

                $commitFiles++;
                $commitAdded += $file['added'];
                $commitDeleted += $file['deleted'];

                $module = SystemModuleCatalog::moduleFor($path);
                $layer = SystemModuleCatalog::layerFor($path);
                $changed = $file['added'] + $file['deleted'];

                $commitModules[$module] = ($commitModules[$module] ?? 0) + max(1, $changed);

                $entry['modules'][$module] ??= ['commits' => 0, 'added' => 0, 'deleted' => 0, 'files' => 0];
                $entry['modules'][$module]['added'] += $file['added'];
                $entry['modules'][$module]['deleted'] += $file['deleted'];
                $entry['days'][$day]['m'][$module] = ($entry['days'][$day]['m'][$module] ?? 0) + $changed;

                $entry['layers'][$layer] ??= ['added' => 0, 'deleted' => 0, 'files' => 0];
                $entry['layers'][$layer]['added'] += $file['added'];
                $entry['layers'][$layer]['deleted'] += $file['deleted'];

                if (! isset($touched[$month][$path])) {
                    $touched[$month][$path] = true;
                    $entry['files_touched']++;
                    $entry['days'][$day]['fn']++;
                    $entry['modules'][$module]['files']++;
                    $entry['layers'][$layer]['files']++;
                }

                if ($file['status'] === 'created') {
                    $entry['files_created']++;
                    $entry['days'][$day]['fc']++;

                    foreach (SystemModuleCatalog::inventoryKindsFor($path) as $kind) {
                        $entry['created_kinds'][$kind] = ($entry['created_kinds'][$kind] ?? 0) + 1;
                    }

                    if (str_starts_with($path, 'tests/') && str_ends_with($path, 'Test.php')) {
                        $entry['days'][$day]['ts']++;
                    }

                    if (str_starts_with($path, 'database/migrations/') && str_ends_with($path, '.php')) {
                        $migration = self::migration($path, $commit['date'], $migrationContents);
                        $entry['migrations'][] = $migration;
                        $entry['tables_created'] += count($migration['created']);
                        $entry['tables_altered'] += count($migration['altered']);
                        $entry['columns'] += $migration['columns'];
                        $entry['indexes'] += $migration['indexes'];
                        $entry['days'][$day]['mg']++;
                        $entry['days'][$day]['tc'] += count($migration['created']);
                        $entry['days'][$day]['ta'] += count($migration['altered']);
                        $entry['days'][$day]['co'] += $migration['columns'];
                        $entry['days'][$day]['ix'] += $migration['indexes'];
                    }
                } elseif ($file['status'] === 'deleted') {
                    $entry['files_deleted']++;
                    $entry['days'][$day]['fd']++;

                    foreach (SystemModuleCatalog::inventoryKindsFor($path) as $kind) {
                        $entry['deleted_kinds'][$kind] = ($entry['deleted_kinds'][$kind] ?? 0) + 1;
                    }
                }
            }

            foreach (array_keys($commitModules) as $module) {
                $entry['modules'][$module]['commits']++;
            }

            $entry['commits']++;
            $entry['added'] += $commitAdded;
            $entry['deleted'] += $commitDeleted;
            $entry['authors'][$commit['author']] = ($entry['authors'][$commit['author']] ?? 0) + 1;
            $entry['days'][$day]['c']++;
            $entry['days'][$day]['a'] += $commitAdded;
            $entry['days'][$day]['d'] += $commitDeleted;

            arsort($commitModules);
            $entry['top_commits'][] = [
                'hash' => substr($commit['hash'], 0, 8),
                'date' => $commit['date'],
                'author' => $commit['author'],
                'subject' => $commit['subject'],
                'added' => $commitAdded,
                'deleted' => $commitDeleted,
                'files' => $commitFiles,
                'module' => array_key_first($commitModules) ?? SystemModuleCatalog::FALLBACK_MODULE,
            ];

            $totalLines += $commitAdded - $commitDeleted;
            $lastCommitAt = $commit['date'];
            unset($entry);
        }

        foreach ($months as $month => $entry) {
            usort($entry['top_commits'], static fn (array $left, array $right): int => ($right['added'] + $right['deleted']) <=> ($left['added'] + $left['deleted']));
            $entry['top_commits'] = array_slice($entry['top_commits'], 0, self::TOP_COMMITS_PER_MONTH);
            $entry['tests_created'] = $entry['created_kinds']['tests'] ?? 0;
            $entry['active_days'] = count(array_filter($entry['days'], static fn (array $day): bool => $day['c'] > 0));
            ksort($entry['days']);
            arsort($entry['authors']);
            $months[$month] = $entry;
        }

        ksort($months);

        $inventory = array_fill_keys(array_keys(SystemModuleCatalog::inventoryKinds()), 0);
        $totalFiles = 0;

        foreach ($trackedFiles as $path) {
            if (SystemModuleCatalog::isGenerated($path)) {
                continue;
            }

            $totalFiles++;

            foreach (SystemModuleCatalog::inventoryKindsFor($path) as $kind) {
                $inventory[$kind]++;
            }
        }

        return [
            'available' => $months !== [],
            'months' => $months,
            'inventory' => $inventory,
            'total_lines' => max(0, $totalLines),
            'total_files' => $totalFiles,
            'last_commit_at' => $lastCommitAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyMonth(): array
    {
        return [
            'commits' => 0,
            'added' => 0,
            'deleted' => 0,
            'files_touched' => 0,
            'files_created' => 0,
            'files_deleted' => 0,
            'active_days' => 0,
            'tests_created' => 0,
            'tables_created' => 0,
            'tables_altered' => 0,
            'columns' => 0,
            'indexes' => 0,
            'authors' => [],
            'days' => [],
            'modules' => [],
            'layers' => [],
            'created_kinds' => [],
            'deleted_kinds' => [],
            'migrations' => [],
            'top_commits' => [],
        ];
    }

    /**
     * c: commits · a/d: líneas agregadas/eliminadas · fn: archivos tocados por
     * primera vez en el mes · fc: archivos creados · ts: pruebas nuevas ·
     * mg: migraciones nuevas · tc/ta: tablas nuevas/modificadas · co: columnas ·
     * ix: índices · fd: archivos retirados · m: líneas cambiadas por módulo.
     *
     * @return array<string, mixed>
     */
    private static function emptyDay(): array
    {
        return ['c' => 0, 'a' => 0, 'd' => 0, 'fn' => 0, 'fc' => 0, 'fd' => 0, 'ts' => 0, 'mg' => 0, 'tc' => 0, 'ta' => 0, 'co' => 0, 'ix' => 0, 'm' => []];
    }

    /**
     * @param  callable(string): ?string  $migrationContents
     * @return array{file: string, date: string, label: string, created: list<string>, altered: list<string>, dropped: list<string>, columns: int, indexes: int, module: string}
     */
    private static function migration(string $path, string $date, callable $migrationContents): array
    {
        $contents = $migrationContents($path);
        $analysis = $contents !== null
            ? SystemMigrationAnalyzer::analyze($path, $contents)
            : ['label' => SystemMigrationAnalyzer::label($path), 'created' => [], 'altered' => [], 'dropped' => [], 'columns' => 0, 'indexes' => 0];

        $firstTable = $analysis['created'][0] ?? $analysis['altered'][0] ?? null;

        return [
            'file' => basename($path),
            'date' => $date,
            ...$analysis,
            'module' => $firstTable !== null ? SystemModuleCatalog::moduleForTable($firstTable) : SystemModuleCatalog::moduleFor($path),
        ];
    }

    /**
     * La migración tal como está en disco; si ya no existe, la versión de HEAD.
     */
    private function migrationContents(string $path): ?string
    {
        $absolute = $this->git->path().DIRECTORY_SEPARATOR.$path;

        if (is_file($absolute) && is_readable($absolute)) {
            $contents = file_get_contents($absolute);

            return $contents === false ? null : $contents;
        }

        return null;
    }
}
