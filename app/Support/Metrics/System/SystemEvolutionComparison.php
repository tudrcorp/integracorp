<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

use Carbon\CarbonImmutable;

/**
 * Convierte el historial mensual en lo que muestra la página «Sistema»: el mes
 * elegido frente a los meses anteriores.
 *
 * Si el mes elegido está en curso, las tarjetas comparan «a la misma fecha»
 * (del día 1 al día de hoy en cada mes) para no castigar un mes a medias, y
 * además proyectan cómo cerraría al ritmo actual. Tablas y gráficos muestran
 * los meses completos y marcan el mes en curso.
 */
final class SystemEvolutionComparison
{
    public const WINDOWS = [3, 6, 12];

    public const DEFAULT_WINDOW = 6;

    private const MONTH_NAMES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Cifras de las tarjetas: [clave del día, clave del mes, etiqueta, ícono, ayuda].
     *
     * @var array<string, array{day: ?string, label: string, icon: string, hint: string, lower_is_better?: bool}>
     */
    private const KPIS = [
        'commits' => ['day' => 'c', 'label' => 'Entregas de código', 'icon' => 'heroicon-o-arrow-up-tray', 'hint' => 'Commits registrados en la rama desplegada (sin fusiones). Cada uno es un paquete de cambios subido al repositorio.'],
        'added' => ['day' => 'a', 'label' => 'Líneas escritas', 'icon' => 'heroicon-o-plus-circle', 'hint' => 'Líneas de código nuevas o reescritas. No cuenta librerías de terceros ni archivos generados.'],
        'deleted' => ['day' => 'd', 'label' => 'Líneas eliminadas', 'icon' => 'heroicon-o-minus-circle', 'hint' => 'Líneas retiradas o reemplazadas: limpieza y refactorización también son trabajo.'],
        'files_touched' => ['day' => 'fn', 'label' => 'Archivos trabajados', 'icon' => 'heroicon-o-document-duplicate', 'hint' => 'Archivos distintos que cambiaron en el mes (cada archivo cuenta una vez).'],
        'files_created' => ['day' => 'fc', 'label' => 'Archivos nuevos', 'icon' => 'heroicon-o-document-plus', 'hint' => 'Archivos que nacieron en el mes: pantallas, reglas, vistas, pruebas, migraciones…'],
        'active_days' => ['day' => null, 'label' => 'Días con entregas', 'icon' => 'heroicon-o-calendar-days', 'hint' => 'Días del mes en que se subió al menos un commit.'],
        'migrations' => ['day' => 'mg', 'label' => 'Cambios a la base de datos', 'icon' => 'heroicon-o-circle-stack', 'hint' => 'Migraciones nuevas: cada una crea o modifica tablas de la base de datos.'],
        'tables_created' => ['day' => 'tc', 'label' => 'Tablas nuevas', 'icon' => 'heroicon-o-table-cells', 'hint' => 'Tablas creadas por las migraciones del mes.'],
        'tests_created' => ['day' => 'ts', 'label' => 'Pruebas nuevas', 'icon' => 'heroicon-o-beaker', 'hint' => 'Archivos de pruebas automatizadas creados: protegen lo construido de regresiones.'],
    ];

    /**
     * Contador diario de cada indicador (ver SystemEvolutionReport::emptyDay()).
     *
     * @var array<string, string>
     */
    private const DAY_KEYS = [
        'commits' => 'c', 'added' => 'a', 'deleted' => 'd', 'files_touched' => 'fn', 'files_created' => 'fc',
        'files_deleted' => 'fd', 'migrations' => 'mg', 'tables_created' => 'tc', 'tables_altered' => 'ta',
        'columns' => 'co', 'indexes' => 'ix', 'tests_created' => 'ts',
    ];

    /**
     * Filas de la tabla comparativa: [etiqueta, ayuda].
     *
     * @var array<string, array{label: string, hint: string}>
     */
    private const TABLE_ROWS = [
        'commits' => ['label' => 'Entregas (commits)', 'hint' => 'Paquetes de cambios subidos.'],
        'added' => ['label' => 'Líneas escritas', 'hint' => 'Líneas nuevas o reescritas.'],
        'deleted' => ['label' => 'Líneas eliminadas', 'hint' => 'Líneas retiradas o reemplazadas.'],
        'net' => ['label' => 'Crecimiento neto (líneas)', 'hint' => 'Escritas menos eliminadas: cuánto creció el código.'],
        'files_touched' => ['label' => 'Archivos trabajados', 'hint' => 'Archivos distintos con cambios.'],
        'files_created' => ['label' => 'Archivos nuevos', 'hint' => 'Archivos creados.'],
        'files_deleted' => ['label' => 'Archivos retirados', 'hint' => 'Archivos eliminados o movidos.'],
        'active_days' => ['label' => 'Días con entregas', 'hint' => 'Días con al menos un commit.'],
        'modules' => ['label' => 'Módulos trabajados', 'hint' => 'Módulos de negocio con cambios.'],
        'migrations' => ['label' => 'Migraciones', 'hint' => 'Cambios a la estructura de la base de datos.'],
        'tables_created' => ['label' => 'Tablas nuevas', 'hint' => 'Tablas creadas.'],
        'tables_altered' => ['label' => 'Tablas modificadas', 'hint' => 'Tablas existentes que recibieron columnas o índices.'],
        'columns' => ['label' => 'Columnas definidas', 'hint' => 'Columnas creadas en tablas nuevas o existentes.'],
        'indexes' => ['label' => 'Índices y llaves', 'hint' => 'Índices, únicos y llaves foráneas (rendimiento e integridad).'],
        'tests_created' => ['label' => 'Pruebas nuevas', 'hint' => 'Archivos de pruebas automatizadas creados.'],
    ];

    /**
     * Meses con actividad, del más reciente al más antiguo, para el selector.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function monthOptions(array $payload, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now();
        $months = array_keys($payload['months'] ?? []);
        $months[] = $today->format('Y-m');
        $months = array_unique($months);
        rsort($months);

        $options = [];

        foreach ($months as $month) {
            $options[$month] = self::monthLabel($month).($month === $today->format('Y-m') ? ' (en curso)' : '');
        }

        return $options;
    }

    public static function normalizeMonth(?string $month, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::now();

        if (! is_string($month) || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month) !== 1 || $month > $today->format('Y-m')) {
            return $today->format('Y-m');
        }

        return $month;
    }

    public static function normalizeWindow(mixed $window): int
    {
        $window = (int) $window;

        return in_array($window, self::WINDOWS, true) ? $window : self::DEFAULT_WINDOW;
    }

    public static function monthLabel(string $month, bool $short = false): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $month);

        if ($date === false) {
            return $month;
        }

        $name = self::MONTH_NAMES[(int) $date->format('n')];

        return $short
            ? ucfirst(mb_substr($name, 0, 3)).' '.$date->format('y')
            : ucfirst($name).' '.$date->format('Y');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $database
     * @return array<string, mixed>
     */
    public static function build(array $payload, string $month, int $window, array $database = [], ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now();
        $month = self::normalizeMonth($month, $today);
        $window = self::normalizeWindow($window);
        $selected = CarbonImmutable::createFromFormat('!Y-m', $month);
        $inProgress = $month === $today->format('Y-m');
        $daysInMonth = (int) $selected->daysInMonth;
        $cutDay = $inProgress ? (int) $today->day : $daysInMonth;

        $previousMonths = [];

        for ($offset = $window; $offset >= 1; $offset--) {
            $previousMonths[] = $selected->subMonthsNoOverflow($offset)->format('Y-m');
        }

        $sequence = [...$previousMonths, $month];
        $months = $payload['months'] ?? [];
        $current = self::month($months, $month);
        $previous = self::month($months, end($previousMonths) ?: $month);

        return [
            'month' => $month,
            'monthLabel' => self::monthLabel($month),
            'previousLabel' => self::monthLabel((string) end($previousMonths)),
            'window' => $window,
            'windowLabel' => 'los '.$window.' meses anteriores ('.self::monthLabel($previousMonths[0], true).' – '.self::monthLabel((string) end($previousMonths), true).')',
            'inProgress' => $inProgress,
            'cutDay' => $cutDay,
            'daysInMonth' => $daysInMonth,
            'hasData' => $current['commits'] > 0,
            'kpis' => self::kpis($months, $month, $previousMonths, $cutDay, $inProgress, $daysInMonth),
            'previousMonth' => self::previousMonthComparison($months, $month, (string) end($previousMonths), $cutDay, $inProgress),
            'series' => self::series($payload, $sequence, $month, $cutDay, $inProgress),
            'table' => self::table($months, $previousMonths, $month),
            'modules' => self::modules($months, $sequence, $month, $previousMonths),
            'layers' => self::layers($current, $months, $previousMonths),
            'inventory' => self::inventory($payload, $sequence, $month),
            'migrations' => self::migrationRows($current['migrations']),
            'database' => self::database($database, $current, $months, $sequence),
            'authors' => self::authors($current),
            'topCommits' => self::topCommits($current['top_commits']),
            'insights' => self::insights($current, $previous, $months, $month, $previousMonths, $cutDay, $inProgress),
            'totals' => [
                'lines' => (int) ($payload['total_lines'] ?? 0),
                'files' => (int) ($payload['total_files'] ?? 0),
                'last_commit_at' => $payload['last_commit_at'] ?? null,
                'generated_at' => $payload['generated_at'] ?? null,
                'head' => isset($payload['head']) ? substr((string) $payload['head'], 0, 8) : null,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $months
     * @return array<string, mixed>
     */
    private static function month(array $months, string $month): array
    {
        $entry = $months[$month] ?? [];

        return $entry + [
            'commits' => 0, 'added' => 0, 'deleted' => 0, 'files_touched' => 0, 'files_created' => 0, 'files_deleted' => 0,
            'active_days' => 0, 'tests_created' => 0, 'tables_created' => 0, 'tables_altered' => 0, 'columns' => 0, 'indexes' => 0,
            'authors' => [], 'days' => [], 'modules' => [], 'layers' => [], 'created_kinds' => [], 'deleted_kinds' => [],
            'migrations' => [], 'top_commits' => [],
        ];
    }

    /**
     * Valor de una cifra del mes hasta el día de corte (incluido).
     *
     * @param  array<string, mixed>  $entry
     */
    private static function valueUntil(array $entry, string $key, int $cutDay): int
    {
        $total = 0;
        $modules = [];

        foreach ($entry['days'] as $day => $values) {
            if ((int) $day > $cutDay) {
                continue;
            }

            if ($key === 'modules') {
                foreach ($values['m'] ?? [] as $module => $changed) {
                    $modules[$module] = true;
                }

                continue;
            }

            $total += match ($key) {
                'active_days' => ($values['c'] ?? 0) > 0 ? 1 : 0,
                'net' => (int) ($values['a'] ?? 0) - (int) ($values['d'] ?? 0),
                default => (int) ($values[self::DAY_KEYS[$key] ?? ''] ?? 0),
            };
        }

        return $key === 'modules' ? count($modules) : $total;
    }

    /**
     * Líneas cambiadas por módulo hasta el día de corte (incluido).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, int>
     */
    private static function modulesUntil(array $entry, int $cutDay): array
    {
        $modules = [];

        foreach ($entry['days'] as $day => $values) {
            if ((int) $day > $cutDay) {
                continue;
            }

            foreach ($values['m'] ?? [] as $module => $changed) {
                $modules[$module] = ($modules[$module] ?? 0) + (int) $changed;
            }
        }

        return $modules;
    }

    /**
     * Comparativa directa con el mes anterior: cada indicador lado a lado, con
     * la diferencia. Si el mes está en curso, el mes anterior se corta en el
     * mismo día y además se muestra completo como referencia.
     *
     * @param  array<string, array<string, mixed>>  $months
     * @return array<string, mixed>
     */
    private static function previousMonthComparison(array $months, string $month, string $previousMonth, int $cutDay, bool $inProgress): array
    {
        $current = self::month($months, $month);
        $previous = self::month($months, $previousMonth);
        $rows = [];

        foreach (self::TABLE_ROWS as $key => $definition) {
            $value = $inProgress ? self::valueUntil($current, $key, $cutDay) : self::tableValue($current, $key);
            $previousValue = $inProgress ? self::valueUntil($previous, $key, $cutDay) : self::tableValue($previous, $key);
            $scale = max(1, abs($value), abs($previousValue));

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'hint' => $definition['hint'],
                'current' => $value,
                'previous' => $previousValue,
                'previousFull' => self::tableValue($previous, $key),
                'difference' => $value - $previousValue,
                'delta' => self::delta($value, $previousValue),
                'currentBar' => (int) round(abs($value) * 100 / $scale),
                'previousBar' => (int) round(abs($previousValue) * 100 / $scale),
            ];
        }

        $catalog = SystemModuleCatalog::modules();
        $currentModules = $inProgress ? self::modulesUntil($current, $cutDay) : array_map(static fn (array $data): int => $data['added'] + $data['deleted'], $current['modules']);
        $previousModules = $inProgress ? self::modulesUntil($previous, $cutDay) : array_map(static fn (array $data): int => $data['added'] + $data['deleted'], $previous['modules']);
        $moduleRows = [];

        foreach ($catalog as $key => $module) {
            $value = (int) ($currentModules[$key] ?? 0);
            $previousValue = (int) ($previousModules[$key] ?? 0);

            if ($value === 0 && $previousValue === 0) {
                continue;
            }

            $moduleRows[] = [
                'key' => $key,
                'label' => $module['label'],
                'color' => $module['color'],
                'current' => $value,
                'previous' => $previousValue,
                'difference' => $value - $previousValue,
                'delta' => self::delta($value, $previousValue),
            ];
        }

        usort($moduleRows, static fn (array $left, array $right): int => max($right['current'], $right['previous']) <=> max($left['current'], $left['previous']));
        $moduleMax = max(1, ...array_map(static fn (array $row): int => max($row['current'], $row['previous']), $moduleRows ?: [['current' => 0, 'previous' => 0]]));

        foreach ($moduleRows as $index => $row) {
            $moduleRows[$index]['currentBar'] = (int) round($row['current'] * 100 / $moduleMax);
            $moduleRows[$index]['previousBar'] = (int) round($row['previous'] * 100 / $moduleMax);
        }

        $gained = array_values(array_filter($moduleRows, static fn (array $row): bool => $row['previous'] === 0 && $row['current'] > 0));
        $dropped = array_values(array_filter($moduleRows, static fn (array $row): bool => $row['current'] === 0 && $row['previous'] > 0));
        $improved = count(array_filter($rows, static fn (array $row): bool => $row['delta']['direction'] === 'up'));
        $declined = count(array_filter($rows, static fn (array $row): bool => $row['delta']['direction'] === 'down'));

        return [
            'currentLabel' => self::monthLabel($month).($inProgress ? ' (al día '.$cutDay.')' : ''),
            'previousLabel' => self::monthLabel($previousMonth).($inProgress ? ' (al día '.$cutDay.')' : ''),
            'previousFullLabel' => self::monthLabel($previousMonth).' completo',
            'inProgress' => $inProgress,
            'rows' => $rows,
            'modules' => $moduleRows,
            'newModules' => array_column($gained, 'label'),
            'pausedModules' => array_column($dropped, 'label'),
            'improved' => $improved,
            'declined' => $declined,
            'same' => count($rows) - $improved - $declined,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $previousMonths
     * @return list<array<string, mixed>>
     */
    private static function kpis(array $months, string $month, array $previousMonths, int $cutDay, bool $inProgress, int $daysInMonth): array
    {
        $current = self::month($months, $month);
        $tiles = [];

        foreach (self::KPIS as $key => $definition) {
            $value = $inProgress ? self::valueUntil($current, $key, $cutDay) : self::tableValue($current, $key);
            $history = array_map(
                static fn (string $previousMonth): int => $inProgress
                    ? self::valueUntil(self::month($months, $previousMonth), $key, $cutDay)
                    : self::tableValue(self::month($months, $previousMonth), $key),
                $previousMonths,
            );

            $previous = (int) end($history);
            $average = $history === [] ? 0.0 : array_sum($history) / count($history);
            $fullHistory = array_map(static fn (string $previousMonth): int => self::tableValue(self::month($months, $previousMonth), $key), $previousMonths);
            $projection = $inProgress && $cutDay > 0 && $key !== 'active_days'
                ? (int) round($value * $daysInMonth / $cutDay)
                : null;

            $tiles[] = [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'hint' => $definition['hint'],
                'value' => $value,
                'previous' => $previous,
                'average' => round($average, 1),
                'vsPrevious' => self::delta($value, $previous),
                'vsAverage' => self::delta($value, $average),
                'projection' => $projection,
                'best' => $fullHistory === [] ? 0 : max($fullHistory),
                'sparkline' => [...$fullHistory, self::tableValue($current, $key)],
            ];
        }

        return $tiles;
    }

    /**
     * @return array{direction: string, text: string, percent: ?float}
     */
    public static function delta(int|float $value, int|float $reference): array
    {
        $difference = $value - $reference;

        if (abs($difference) < 0.05) {
            return ['direction' => 'flat', 'text' => 'Igual', 'percent' => 0.0];
        }

        $direction = $difference > 0 ? 'up' : 'down';

        if ($reference <= 0) {
            return ['direction' => $direction, 'text' => 'Nuevo', 'percent' => null];
        }

        $percent = $difference * 100 / $reference;

        return [
            'direction' => $direction,
            'text' => ($difference > 0 ? '+' : '−').self::number(abs($percent), abs($percent) < 10 ? 1 : 0).' %',
            'percent' => round($percent, 1),
        ];
    }

    /**
     * Serie mensual para el gráfico de evolución (meses completos).
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $sequence
     * @return list<array<string, mixed>>
     */
    private static function series(array $payload, array $sequence, string $selectedMonth, int $cutDay, bool $inProgress): array
    {
        $months = $payload['months'] ?? [];
        $lines = self::linesAtMonthEnd($payload, $sequence);
        $series = [];

        foreach ($sequence as $month) {
            $entry = self::month($months, $month);
            $series[] = [
                'month' => $month,
                'label' => self::monthLabel($month, true),
                'selected' => $month === $selectedMonth,
                'partial' => $month === $selectedMonth && $inProgress,
                'commits' => (int) $entry['commits'],
                'added' => (int) $entry['added'],
                'deleted' => (int) $entry['deleted'],
                'files_touched' => (int) $entry['files_touched'],
                'files_created' => (int) $entry['files_created'],
                'migrations' => count($entry['migrations']),
                'tables_created' => (int) $entry['tables_created'],
                'tests_created' => (int) $entry['tests_created'],
                'active_days' => (int) $entry['active_days'],
                'lines_total' => $lines[$month] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Tamaño del código (líneas) al cierre de cada mes: total de hoy menos lo
     * que creció después.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $sequence
     * @return array<string, int>
     */
    private static function linesAtMonthEnd(array $payload, array $sequence): array
    {
        $total = (int) ($payload['total_lines'] ?? 0);
        $months = $payload['months'] ?? [];
        $result = [];

        foreach ($sequence as $month) {
            $after = 0;

            foreach ($months as $key => $entry) {
                if ($key > $month) {
                    $after += (int) $entry['added'] - (int) $entry['deleted'];
                }
            }

            $result[$month] = max(0, $total - $after);
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $previousMonths
     * @return array{columns: list<array{month: string, label: string, selected: bool}>, rows: list<array<string, mixed>>}
     */
    private static function table(array $months, array $previousMonths, string $month): array
    {
        $sequence = [...$previousMonths, $month];
        $rows = [];

        foreach (self::TABLE_ROWS as $key => $definition) {
            $values = array_map(static fn (string $item): int => self::tableValue(self::month($months, $item), $key), $sequence);
            $history = array_slice($values, 0, -1);
            $value = (int) end($values);
            $average = $history === [] ? 0.0 : array_sum($history) / count($history);
            $max = $values === [] ? 0 : max($values);

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'hint' => $definition['hint'],
                'values' => $values,
                'max' => $max,
                'average' => round($average, 1),
                'delta' => self::delta($value, $average),
                'isRecord' => $value > 0 && $value === $max && count(array_filter($values, static fn (int $item): bool => $item === $max)) === 1,
            ];
        }

        return [
            'columns' => array_map(static fn (string $item): array => [
                'month' => $item,
                'label' => self::monthLabel($item, true),
                'selected' => $item === $month,
            ], $sequence),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function tableValue(array $entry, string $key): int
    {
        return match ($key) {
            'net' => (int) $entry['added'] - (int) $entry['deleted'],
            'modules' => count($entry['modules']),
            'migrations' => count($entry['migrations']),
            default => (int) ($entry[$key] ?? 0),
        };
    }

    /**
     * Trabajo por módulo: el mes elegido (líneas cambiadas, commits, archivos),
     * su peso, su promedio y la serie de meses para el mapa de calor.
     *
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $sequence
     * @param  list<string>  $previousMonths
     * @return array{rows: list<array<string, mixed>>, columns: list<array{label: string, selected: bool}>, total: int}
     */
    private static function modules(array $months, array $sequence, string $month, array $previousMonths): array
    {
        $catalog = SystemModuleCatalog::modules();
        $current = self::month($months, $month);
        $total = 0;

        foreach ($current['modules'] as $module) {
            $total += $module['added'] + $module['deleted'];
        }

        $rows = [];

        foreach ($catalog as $key => $module) {
            $values = array_map(static function (string $item) use ($months, $key): int {
                $data = self::month($months, $item)['modules'][$key] ?? null;

                return $data === null ? 0 : $data['added'] + $data['deleted'];
            }, $sequence);

            if (array_sum($values) === 0) {
                continue;
            }

            $data = $current['modules'][$key] ?? ['commits' => 0, 'added' => 0, 'deleted' => 0, 'files' => 0];
            $changed = $data['added'] + $data['deleted'];
            $history = array_slice($values, 0, count($previousMonths));
            $average = $history === [] ? 0.0 : array_sum($history) / count($history);
            $rowMax = max($values);

            $rows[] = [
                'key' => $key,
                'label' => $module['label'],
                'color' => $module['color'],
                'changed' => $changed,
                'added' => $data['added'],
                'deleted' => $data['deleted'],
                'commits' => $data['commits'],
                'files' => $data['files'],
                'share' => $total > 0 ? round($changed * 100 / $total, 1) : 0.0,
                'average' => (int) round($average),
                'delta' => self::delta($changed, $average),
                'cells' => array_map(static fn (int $value): array => [
                    'value' => $value,
                    'intensity' => $rowMax > 0 ? round($value / $rowMax, 2) : 0.0,
                ], $values),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$right['changed'], array_sum(array_column($right['cells'], 'value'))] <=> [$left['changed'], array_sum(array_column($left['cells'], 'value'))]);

        return [
            'rows' => $rows,
            'columns' => array_map(static fn (string $item): array => ['label' => self::monthLabel($item, true), 'selected' => $item === $month], $sequence),
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $previousMonths
     * @return list<array<string, mixed>>
     */
    private static function layers(array $current, array $months, array $previousMonths): array
    {
        $total = 0;

        foreach ($current['layers'] as $layer) {
            $total += $layer['added'] + $layer['deleted'];
        }

        $rows = [];

        foreach (SystemModuleCatalog::layers() as $key => $layer) {
            $data = $current['layers'][$key] ?? ['added' => 0, 'deleted' => 0, 'files' => 0];
            $changed = $data['added'] + $data['deleted'];
            $history = array_map(static function (string $item) use ($months, $key): int {
                $value = self::month($months, $item)['layers'][$key] ?? null;

                return $value === null ? 0 : $value['added'] + $value['deleted'];
            }, $previousMonths);
            $average = $history === [] ? 0.0 : array_sum($history) / count($history);

            if ($changed === 0 && $average <= 0) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $layer['label'],
                'hint' => $layer['hint'],
                'changed' => $changed,
                'files' => $data['files'],
                'share' => $total > 0 ? round($changed * 100 / $total, 1) : 0.0,
                'average' => (int) round($average),
                'delta' => self::delta($changed, $average),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['changed'] <=> $left['changed']);

        return $rows;
    }

    /**
     * Piezas del sistema hoy, al cierre del mes elegido y al inicio de la
     * ventana, con lo creado en el mes.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $sequence
     * @return list<array<string, mixed>>
     */
    private static function inventory(array $payload, array $sequence, string $month): array
    {
        $months = $payload['months'] ?? [];
        $firstMonth = $sequence[0];
        $rows = [];

        foreach (SystemModuleCatalog::inventoryKinds() as $kind => $definition) {
            $today = (int) ($payload['inventory'][$kind] ?? 0);
            $atEnd = static function (string $limit) use ($months, $kind, $today): int {
                $count = $today;

                foreach ($months as $key => $entry) {
                    if ($key > $limit) {
                        $count -= (int) ($entry['created_kinds'][$kind] ?? 0);
                        $count += (int) ($entry['deleted_kinds'][$kind] ?? 0);
                    }
                }

                return max(0, $count);
            };

            $beforeWindow = $atEnd(CarbonImmutable::createFromFormat('!Y-m', $firstMonth)->subMonthNoOverflow()->format('Y-m'));
            $endOfMonth = $atEnd($month);
            $created = (int) (self::month($months, $month)['created_kinds'][$kind] ?? 0);

            $rows[] = [
                'key' => $kind,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'today' => $today,
                'end_of_month' => $endOfMonth,
                'before_window' => $beforeWindow,
                'created' => $created,
                'growth' => self::delta($endOfMonth, $beforeWindow),
                'series' => array_map($atEnd, $sequence),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $migrations
     * @return list<array<string, mixed>>
     */
    private static function migrationRows(array $migrations): array
    {
        $modules = SystemModuleCatalog::modules();

        usort($migrations, static fn (array $left, array $right): int => strcmp((string) $right['date'], (string) $left['date']));

        return array_map(static fn (array $migration): array => [
            ...$migration,
            'date_text' => CarbonImmutable::parse((string) $migration['date'])->format('d/m/Y'),
            'module_label' => $modules[$migration['module']]['label'] ?? $modules[SystemModuleCatalog::FALLBACK_MODULE]['label'],
            'module_color' => $modules[$migration['module']]['color'] ?? $modules[SystemModuleCatalog::FALLBACK_MODULE]['color'],
        ], $migrations);
    }

    /**
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $current
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $sequence
     * @return array<string, mixed>
     */
    private static function database(array $database, array $current, array $months, array $sequence): array
    {
        $catalog = SystemModuleCatalog::modules();
        $byModule = [];

        foreach ($current['migrations'] as $migration) {
            $byModule[$migration['module']] ??= ['migrations' => 0, 'tables' => 0, 'columns' => 0];
            $byModule[$migration['module']]['migrations']++;
            $byModule[$migration['module']]['tables'] += count($migration['created']);
            $byModule[$migration['module']]['columns'] += (int) $migration['columns'];
        }

        uasort($byModule, static fn (array $left, array $right): int => [$right['migrations'], $right['columns']] <=> [$left['migrations'], $left['columns']]);

        $tablesToday = (int) ($database['tables'] ?? 0);
        $tablesSeries = [];

        if ($tablesToday > 0) {
            foreach ($sequence as $month) {
                $after = 0;

                foreach ($months as $key => $entry) {
                    if ($key > $month) {
                        $after += (int) $entry['tables_created'];
                    }
                }

                $tablesSeries[] = ['label' => self::monthLabel($month, true), 'value' => max(0, $tablesToday - $after)];
            }
        }

        $snapshotModules = [];

        foreach ($database['modules'] ?? [] as $key => $module) {
            $snapshotModules[] = [
                'label' => $catalog[$key]['label'] ?? $key,
                'color' => $catalog[$key]['color'] ?? '#94a3b8',
                ...$module,
            ];
        }

        usort($snapshotModules, static fn (array $left, array $right): int => $right['size_mb'] <=> $left['size_mb']);

        return [
            'available' => (bool) ($database['available'] ?? false),
            'tables' => $tablesToday,
            'size_mb' => (float) ($database['size_mb'] ?? 0),
            'rows' => (int) ($database['rows'] ?? 0),
            'largest' => array_map(static fn (array $table): array => [
                ...$table,
                'module_label' => $catalog[$table['module']]['label'] ?? $table['module'],
            ], $database['largest'] ?? []),
            'modules' => $snapshotModules,
            'tablesSeries' => $tablesSeries,
            'monthByModule' => array_map(
                static fn (string $key, array $values): array => ['label' => $catalog[$key]['label'] ?? $key, 'color' => $catalog[$key]['color'] ?? '#94a3b8', ...$values],
                array_keys($byModule),
                array_values($byModule),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return list<array{name: string, commits: int, share: float}>
     */
    private static function authors(array $current): array
    {
        $total = max(1, (int) $current['commits']);
        $rows = [];

        foreach ($current['authors'] as $name => $commits) {
            $rows[] = ['name' => (string) $name, 'commits' => (int) $commits, 'share' => round($commits * 100 / $total, 1)];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $commits
     * @return list<array<string, mixed>>
     */
    private static function topCommits(array $commits): array
    {
        $modules = SystemModuleCatalog::modules();

        return array_map(static fn (array $commit): array => [
            ...$commit,
            'date_text' => CarbonImmutable::parse((string) $commit['date'])->format('d/m/Y H:i'),
            'module_label' => $modules[$commit['module']]['label'] ?? $commit['module'],
            'module_color' => $modules[$commit['module']]['color'] ?? '#94a3b8',
            'subject_short' => mb_strlen((string) $commit['subject']) > 170 ? mb_substr((string) $commit['subject'], 0, 167).'…' : $commit['subject'],
        ], $commits);
    }

    /**
     * Resumen en lenguaje natural del mes.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, array<string, mixed>>  $months
     * @param  list<string>  $previousMonths
     * @return list<array{icon: string, tone: string, text: string}>
     */
    private static function insights(array $current, array $previous, array $months, string $month, array $previousMonths, int $cutDay, bool $inProgress): array
    {
        $label = self::monthLabel($month);
        $previousLabel = self::monthLabel((string) end($previousMonths));

        if ((int) $current['commits'] === 0) {
            return [[
                'icon' => 'heroicon-o-information-circle',
                'tone' => 'gray',
                'text' => 'En '.$label.' todavía no hay entregas de código registradas en la rama desplegada.',
            ]];
        }

        $insights = [];
        $commits = $inProgress ? self::valueUntil($current, 'commits', $cutDay) : (int) $current['commits'];
        $previousCommits = $inProgress ? self::valueUntil($previous, 'commits', $cutDay) : (int) $previous['commits'];
        $delta = self::delta($commits, $previousCommits);
        $scope = $inProgress ? ' (del 1 al '.$cutDay.')' : '';
        $comparison = match ($delta['direction']) {
            'up' => $delta['percent'] === null ? 'cuando '.$previousLabel.' no tuvo ninguna a la misma altura' : $delta['text'].' que '.$previousLabel.($inProgress ? ' a la misma fecha' : ''),
            'down' => $delta['text'].' que '.$previousLabel.($inProgress ? ' a la misma fecha' : ''),
            default => 'lo mismo que '.$previousLabel.($inProgress ? ' a la misma fecha' : ''),
        };

        $insights[] = [
            'icon' => 'heroicon-o-arrow-up-tray',
            'tone' => $delta['direction'] === 'down' ? 'warning' : 'success',
            'text' => 'En '.$label.$scope.' se hicieron '.self::number($commits).' entregas de código: '.$comparison.'.',
        ];

        if ($inProgress) {
            $projected = (int) round((int) $current['commits'] * ((int) CarbonImmutable::createFromFormat('!Y-m', $month)->daysInMonth) / max(1, $cutDay));
            $history = array_map(static fn (string $item): int => (int) self::month($months, $item)['commits'], $previousMonths);
            $average = $history === [] ? 0 : array_sum($history) / count($history);
            $insights[] = [
                'icon' => 'heroicon-o-forward',
                'tone' => 'info',
                'text' => 'Al ritmo actual, '.$label.' cerraría con unas '.self::number($projected).' entregas; el promedio mensual de los meses comparados es '.self::number($average, 0).'.',
            ];
        }

        $net = (int) $current['added'] - (int) $current['deleted'];
        $insights[] = [
            'icon' => 'heroicon-o-code-bracket',
            'tone' => 'info',
            'text' => 'Se escribieron '.self::number((int) $current['added']).' líneas y se retiraron '.self::number((int) $current['deleted']).': el código '.($net >= 0 ? 'creció' : 'se redujo').' '.self::number(abs($net)).' líneas en '.self::number((int) $current['files_touched']).' archivos.',
        ];

        $modules = $current['modules'];
        uasort($modules, static fn (array $left, array $right): int => ($right['added'] + $right['deleted']) <=> ($left['added'] + $left['deleted']));
        $catalog = SystemModuleCatalog::modules();
        $totalChanged = array_sum(array_map(static fn (array $module): int => $module['added'] + $module['deleted'], $modules));
        $top = array_slice($modules, 0, 3, true);

        if ($top !== [] && $totalChanged > 0) {
            $parts = [];

            foreach ($top as $key => $module) {
                $parts[] = ($catalog[$key]['label'] ?? $key).' ('.self::number(($module['added'] + $module['deleted']) * 100 / $totalChanged, 0).' %)';
            }

            $insights[] = [
                'icon' => 'heroicon-o-squares-2x2',
                'tone' => 'primary',
                'text' => 'Se trabajó en '.count($modules).' módulos. Los que más cambiaron: '.implode(', ', $parts).'.',
            ];
        }

        $migrations = count($current['migrations']);

        if ($migrations > 0) {
            $insights[] = [
                'icon' => 'heroicon-o-circle-stack',
                'tone' => 'info',
                'text' => 'La base de datos recibió '.self::number($migrations).' '.($migrations === 1 ? 'migración' : 'migraciones').': '
                    .self::number((int) $current['tables_created']).' tablas nuevas, '
                    .self::number((int) $current['tables_altered']).' tablas modificadas y '
                    .self::number((int) $current['columns']).' columnas definidas.',
            ];
        }

        if ((int) $current['tests_created'] > 0) {
            $insights[] = [
                'icon' => 'heroicon-o-beaker',
                'tone' => 'success',
                'text' => 'Se agregaron '.self::number((int) $current['tests_created']).' archivos de pruebas automatizadas que protegen lo construido.',
            ];
        }

        $best = 0;
        $bestMonth = null;

        foreach ($months as $key => $entry) {
            if ($key !== $month && (int) $entry['commits'] > $best) {
                $best = (int) $entry['commits'];
                $bestMonth = $key;
            }
        }

        if (! $inProgress && (int) $current['commits'] > $best && $bestMonth !== null) {
            $insights[] = [
                'icon' => 'heroicon-o-trophy',
                'tone' => 'success',
                'text' => $label.' es el mes con más entregas del historial (el récord anterior era '.self::monthLabel($bestMonth).', con '.self::number($best).').',
            ];
        }

        return $insights;
    }

    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}
