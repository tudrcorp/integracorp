<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Foto de la base de datos a hoy: cuántas tablas tiene, cuánto pesa, cuántos
 * registros guarda (estimado del motor) y cómo se reparten por módulo. Solo
 * lee `information_schema`; con otro motor (SQLite de pruebas) no hay foto.
 */
final class SystemDatabaseSnapshot
{
    private const CACHE_KEY = 'metrics:system:database-snapshot:v1';

    private const CACHE_SECONDS = 1800;

    /**
     * @return array{available: bool, database: string, tables: int, size_mb: float, rows: int, largest: list<array{table: string, module: string, rows: int, size_mb: float}>, modules: array<string, array{tables: int, rows: int, size_mb: float}>, table_names: list<string>}
     */
    public static function current(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static fn (): array => self::read());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{available: bool, database: string, tables: int, size_mb: float, rows: int, largest: list<array{table: string, module: string, rows: int, size_mb: float}>, modules: array<string, array{tables: int, rows: int, size_mb: float}>, table_names: list<string>}
     */
    private static function read(): array
    {
        $empty = ['available' => false, 'database' => '', 'tables' => 0, 'size_mb' => 0.0, 'rows' => 0, 'largest' => [], 'modules' => [], 'table_names' => []];

        try {
            $connection = DB::connection();

            if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                return $empty;
            }

            $database = (string) $connection->getDatabaseName();
            $rows = $connection->table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_TYPE', 'BASE TABLE')
                ->get(['TABLE_NAME as name', 'TABLE_ROWS as rows', 'DATA_LENGTH as data', 'INDEX_LENGTH as index_size']);
        } catch (Throwable $exception) {
            Log::warning('Métricas · Sistema: no se pudo leer information_schema.', ['error' => $exception->getMessage()]);

            return $empty;
        }

        $tables = [];
        $modules = [];

        foreach ($rows as $row) {
            $name = (string) $row->name;
            $module = SystemModuleCatalog::moduleForTable($name);
            $sizeMb = round(((int) $row->data + (int) $row->index_size) / 1048576, 2);
            $tableRows = (int) $row->rows;

            $tables[] = ['table' => $name, 'module' => $module, 'rows' => $tableRows, 'size_mb' => $sizeMb];
            $modules[$module] ??= ['tables' => 0, 'rows' => 0, 'size_mb' => 0.0];
            $modules[$module]['tables']++;
            $modules[$module]['rows'] += $tableRows;
            $modules[$module]['size_mb'] += $sizeMb;
        }

        usort($tables, static fn (array $left, array $right): int => $right['size_mb'] <=> $left['size_mb']);

        return [
            'available' => true,
            'database' => $database,
            'tables' => count($tables),
            'size_mb' => round(array_sum(array_column($tables, 'size_mb')), 1),
            'rows' => array_sum(array_column($tables, 'rows')),
            'largest' => array_slice($tables, 0, 10),
            'modules' => array_map(static fn (array $module): array => [...$module, 'size_mb' => round($module['size_mb'], 1)], $modules),
            'table_names' => array_column($tables, 'table'),
        ];
    }
}
