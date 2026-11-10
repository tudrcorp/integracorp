<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Lee el historial de git del repositorio desplegado: solo lectura, sin tocar
 * el árbol de trabajo. Si el servidor no tiene git o la carpeta no es un
 * repositorio, responde «no disponible» en vez de fallar.
 *
 * Se lee la rama desplegada (`HEAD`) sin commits de fusión y sin detección de
 * renombres: un archivo movido cuenta como eliminado y creado, igual que en el
 * inventario de archivos.
 */
final class SystemGitHistory
{
    private const FIELD = "\x1f";

    private const COMMIT_MARK = '@@';

    private const TIMEOUT_SECONDS = 90;

    public function __construct(private readonly ?string $repositoryPath = null) {}

    public function path(): string
    {
        return $this->repositoryPath ?? base_path();
    }

    public function isAvailable(): bool
    {
        return $this->head() !== null;
    }

    /**
     * Hash del último commit de la rama desplegada, o null sin git.
     */
    public function head(): ?string
    {
        $output = $this->git(['rev-parse', 'HEAD'], 10);
        $hash = $output !== null ? trim($output) : '';

        return preg_match('/^[0-9a-f]{40}$/', $hash) === 1 ? $hash : null;
    }

    /**
     * @return list<array{hash: string, date: string, author: string, subject: string, files: list<array{path: string, added: int, deleted: int, binary: bool, status: string}>}>
     */
    public function commits(): array
    {
        $output = $this->git([
            'log', 'HEAD', '--no-merges', '--no-renames', '--numstat', '--summary',
            '--format='.self::COMMIT_MARK.'%H'.self::FIELD.'%aI'.self::FIELD.'%an'.self::FIELD.'%s',
        ], self::TIMEOUT_SECONDS);

        return $output === null ? [] : self::parseLog($output);
    }

    /**
     * Archivos versionados hoy en la rama desplegada.
     *
     * @return list<string>
     */
    public function trackedFiles(): array
    {
        $output = $this->git(['ls-tree', '-r', '--name-only', 'HEAD'], 30);

        if ($output === null) {
            return [];
        }

        return array_values(array_filter(explode("\n", $output), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Contenido de un archivo tal como quedó en la rama desplegada.
     */
    public function fileAtHead(string $path): ?string
    {
        return $this->git(['show', 'HEAD:'.$path], 10);
    }

    /**
     * Cambios del árbol de trabajo que aún no están en un commit (no cuentan en
     * las estadísticas, pero conviene avisarlo).
     *
     * @return array{files: int, added: int, deleted: int}
     */
    public function uncommitted(): array
    {
        $output = $this->git(['diff', '--numstat', 'HEAD'], 20);
        $summary = ['files' => 0, 'added' => 0, 'deleted' => 0];

        foreach (explode("\n", (string) $output) as $line) {
            if (preg_match('/^(\d+|-)\t(\d+|-)\t(.+)$/', $line, $matches) !== 1 || SystemModuleCatalog::isGenerated($matches[3])) {
                continue;
            }

            $summary['files']++;
            $summary['added'] += $matches[1] === '-' ? 0 : (int) $matches[1];
            $summary['deleted'] += $matches[2] === '-' ? 0 : (int) $matches[2];
        }

        return $summary;
    }

    /**
     * Convierte la salida de `git log --numstat --summary` en commits.
     *
     * @return list<array{hash: string, date: string, author: string, subject: string, files: list<array{path: string, added: int, deleted: int, binary: bool, status: string}>}>
     */
    public static function parseLog(string $output): array
    {
        $commits = [];
        $current = null;
        $index = [];

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, self::COMMIT_MARK)) {
                if ($current !== null) {
                    $commits[] = $current;
                }

                $parts = explode(self::FIELD, substr($line, strlen(self::COMMIT_MARK)), 4);

                if (count($parts) < 4 || preg_match('/^[0-9a-f]{40}$/', $parts[0]) !== 1) {
                    $current = null;

                    continue;
                }

                $current = ['hash' => $parts[0], 'date' => $parts[1], 'author' => trim($parts[2]), 'subject' => trim($parts[3]), 'files' => []];
                $index = [];

                continue;
            }

            if ($current === null || $line === '') {
                continue;
            }

            if (preg_match('/^(\d+|-)\t(\d+|-)\t(.+)$/', $line, $matches) === 1) {
                $index[$matches[3]] = count($current['files']);
                $current['files'][] = [
                    'path' => $matches[3],
                    'added' => $matches[1] === '-' ? 0 : (int) $matches[1],
                    'deleted' => $matches[2] === '-' ? 0 : (int) $matches[2],
                    'binary' => $matches[1] === '-',
                    'status' => 'modified',
                ];

                continue;
            }

            if (preg_match('/^ (create|delete) mode \d+ (.+)$/', $line, $matches) === 1 && isset($index[$matches[2]])) {
                $current['files'][$index[$matches[2]]]['status'] = $matches[1] === 'create' ? 'created' : 'deleted';
            }
        }

        if ($current !== null) {
            $commits[] = $current;
        }

        return $commits;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments, int $timeout): ?string
    {
        try {
            $result = Process::path($this->path())
                ->timeout($timeout)
                ->env(['GIT_OPTIONAL_LOCKS' => '0', 'LC_ALL' => 'C'])
                ->run(array_merge(['git', '-c', 'safe.directory='.$this->path(), '-c', 'core.quotepath=off'], $arguments));
        } catch (Throwable $exception) {
            Log::warning('Métricas · Sistema: no se pudo ejecutar git.', ['error' => $exception->getMessage()]);

            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        return $result->output();
    }
}
