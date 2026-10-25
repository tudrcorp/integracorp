<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use App\Models\OperationServiceStatistic;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationsDashboardMetrics;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del Dashboard de Operaciones, siempre agregados en la base.
 *
 * Fuente: la tabla de estadísticas (`operation_service_statistics`), con la fecha
 * del servicio (o la de inicio si falta). Respeta el alcance de
 * {@see OperationsDashboardMetrics::statisticsQuery()}: un proveedor o ATENMEDI
 * solo ven lo suyo. Cada resultado se cachea un minuto por alcance y período.
 */
final class OperationsDashboardAnalytics
{
    public const CACHE_SECONDS = 60;

    public const DATE_SQL = 'COALESCE(service_on, started_on)';

    public const OPEN_STATUSES = ['PENDIENTE', 'EN GESTION'];

    public const FINISHED_STATUS = 'FINALIZADO';

    /** Orden de trabajo del estatus: así se lee el anillo. */
    public const STATUS_ORDER = ['PENDIENTE', 'EN GESTION', 'FINALIZADO', 'REASIGNADO A TDG'];

    public const BUSINESS_LINE_ORDER = ['CORPORATIVOS', 'INDIVIDUALES'];

    /**
     * @var array<string, array{0: int, 1: int}> etiqueta => [desde, hasta]
     */
    public const AGE_BANDS = [
        '0–12' => [0, 12],
        '13–17' => [13, 17],
        '18–29' => [18, 29],
        '30–44' => [30, 44],
        '45–59' => [45, 59],
        '60–74' => [60, 74],
        '75+' => [75, 200],
    ];

    public const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    /**
     * Indicadores principales del período y del período anterior.
     *
     * @return array{current: array<string, int|float>, previous: array<string, int|float>, sparklines: array<string, list<int>>}
     */
    public static function kpis(OperationsDashboardPeriod $period): array
    {
        return self::remember('kpis', $period, static function () use ($period): array {
            return [
                'current' => self::kpiValues($period->from, $period->to),
                'previous' => self::kpiValues($period->previousFrom, $period->previousTo),
                'sparklines' => [
                    'services' => self::dailySeries($period->from, $period->to, null),
                    'finished' => self::dailySeries($period->from, $period->to, self::FINISHED_STATUS),
                    'patients' => self::dailySeries($period->from, $period->to, null, OperationsDashboardMetrics::patientKeySql()),
                    'cases' => self::dailySeries($period->from, $period->to, null, 'telemedicine_case_id'),
                ],
            ];
        });
    }

    /**
     * Servicios por día, semana o mes en el período, con el período anterior
     * alineado por posición (día 1 con día 1).
     *
     * @return array{labels: list<string>, current: list<int>, previous: list<int>, previous_labels: list<string>}
     */
    public static function trend(OperationsDashboardPeriod $period): array
    {
        return self::remember('trend', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $current = self::bucketed($period->from, $period->to, $granularity);
            $previous = self::bucketed($period->previousFrom, $period->previousTo, $granularity);
            $length = count($current['labels']);

            return [
                'labels' => $current['labels'],
                'current' => $current['values'],
                'previous' => array_slice(array_pad($previous['values'], $length, 0), 0, $length),
                'previous_labels' => array_slice(array_pad($previous['labels'], $length, ''), 0, $length),
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byStatus(OperationsDashboardPeriod $period): array
    {
        return self::remember('status', $period, static fn (): array => self::grouped($period, 'service_status', 'Sin estatus', self::STATUS_ORDER));
    }

    /**
     * @return array<string, int>
     */
    public static function byServiceType(OperationsDashboardPeriod $period): array
    {
        return self::remember('service_type', $period, static function () use ($period): array {
            $counts = self::grouped($period, 'service_type', 'Sin tipo');
            arsort($counts);

            return $counts;
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byBusinessLine(OperationsDashboardPeriod $period): array
    {
        return self::remember('business_line', $period, static fn (): array => self::grouped($period, 'business_line', 'Sin línea', self::BUSINESS_LINE_ORDER));
    }

    /**
     * Estados con más servicios; el resto se agrupa en «Otros».
     *
     * @return array{rows: list<array{label: string, value: int}>, others: int, total: int}
     */
    public static function byState(OperationsDashboardPeriod $period, int $limit = 8): array
    {
        return self::remember('state_'.$limit, $period, static function () use ($period, $limit): array {
            $counts = self::grouped($period, 'state', 'Sin estado');
            arsort($counts);

            return self::ranked($counts, $limit);
        });
    }

    /**
     * Servicios específicos más solicitados.
     *
     * @return array{rows: list<array{label: string, value: int}>, others: int, total: int}
     */
    public static function topServices(OperationsDashboardPeriod $period, int $limit = 10): array
    {
        return self::remember('top_services_'.$limit, $period, static function () use ($period, $limit): array {
            $label = "COALESCE(NULLIF(TRIM(specific_service), ''), NULLIF(TRIM(service), ''), 'Sin servicio')";

            $counts = self::inPeriod($period->from, $period->to)
                ->selectRaw($label.' as grouping_key, COUNT(*) as total')
                ->groupByRaw($label)
                ->orderByDesc('total')
                ->pluck('total', 'grouping_key')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all();

            return self::ranked($counts, $limit);
        });
    }

    /**
     * Pacientes con más servicios en el período.
     *
     * @return list<array{key: string, label: string, value: int, cases: int}>
     */
    public static function topPatients(OperationsDashboardPeriod $period, int $limit = 10): array
    {
        return self::remember('top_patients_'.$limit, $period, static function () use ($period, $limit): array {
            $key = OperationsDashboardMetrics::patientKeySql();

            return self::inPeriod($period->from, $period->to)
                ->selectRaw($key.' as patient_key')
                ->selectRaw('MAX(patient_name) as full_name')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('COUNT(DISTINCT telemedicine_case_id) as cases')
                ->groupByRaw($key)
                ->orderByDesc('total')
                ->limit($limit)
                ->get()
                ->map(static fn (object $row): array => [
                    'key' => (string) $row->patient_key,
                    'label' => filled($row->full_name) ? (string) $row->full_name : 'Paciente sin nombre',
                    'value' => (int) $row->total,
                    'cases' => (int) $row->cases,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Pacientes distintos atendidos por franja de edad.
     *
     * @return array<string, int>
     */
    public static function ageBands(OperationsDashboardPeriod $period): array
    {
        return self::remember('age_bands', $period, static function () use ($period): array {
            $case = self::ageBandSql();
            $rows = self::inPeriod($period->from, $period->to)
                ->whereNotNull('patient_age')
                ->selectRaw($case.' as band')
                ->selectRaw('COUNT(DISTINCT '.OperationsDashboardMetrics::patientKeySql().') as total')
                ->groupByRaw($case)
                ->pluck('total', 'band');

            $bands = [];

            foreach (array_keys(self::AGE_BANDS) as $label) {
                $bands[$label] = (int) ($rows[$label] ?? 0);
            }

            return $bands;
        });
    }

    /**
     * Carga operativa: servicios por día de la semana (lunes primero) y hora de inicio.
     *
     * @return array{matrix: list<list<int>>, max: int, total: int}
     */
    public static function heatmap(OperationsDashboardPeriod $period): array
    {
        return self::remember('heatmap', $period, static function () use ($period): array {
            $rows = self::inPeriod($period->from, $period->to)
                ->whereNotNull('started_at_time')
                ->selectRaw('WEEKDAY('.self::DATE_SQL.') as weekday')
                ->selectRaw('HOUR(started_at_time) as hour')
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw('WEEKDAY('.self::DATE_SQL.'), HOUR(started_at_time)')
                ->get();

            $matrix = array_fill(0, 7, array_fill(0, 24, 0));

            foreach ($rows as $row) {
                $weekday = (int) $row->weekday;
                $hour = (int) $row->hour;

                if ($weekday >= 0 && $weekday < 7 && $hour >= 0 && $hour < 24) {
                    $matrix[$weekday][$hour] = (int) $row->total;
                }
            }

            $flat = array_merge(...$matrix);

            return ['matrix' => $matrix, 'max' => max($flat ?: [0]), 'total' => array_sum($flat)];
        });
    }

    /**
     * Detalle de un corte del dashboard (lo que se abre al hacer clic).
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function detail(OperationsDashboardPeriod $period, string $dimension, string $value, int $limit = 200): array
    {
        $query = self::inPeriod($period->from, $period->to);

        if (! self::applyDimension($query, $dimension, $value)) {
            return ['rows' => [], 'total' => 0];
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByRaw(self::DATE_SQL.' DESC')
            ->orderByDesc('started_at_time')
            ->limit($limit)
            ->get([
                'id', 'telemedicine_case_id', 'case_code', 'service_on', 'started_on', 'started_at_time', 'patient_name',
                'service', 'specific_service', 'service_type', 'service_status', 'business_line', 'state', 'city', 'service_provider',
            ])
            ->map(static fn (OperationServiceStatistic $row): array => [
                'id' => (int) $row->id,
                'case_id' => $row->telemedicine_case_id !== null ? (int) $row->telemedicine_case_id : null,
                'case_code' => $row->case_code,
                'date' => self::formatDate($row->service_on ?? $row->started_on),
                'time' => filled($row->started_at_time) ? substr((string) $row->started_at_time, 0, 5) : null,
                'patient' => $row->patient_name,
                'service' => filled($row->specific_service) ? $row->specific_service : $row->service,
                'type' => $row->service_type,
                'status' => $row->service_status,
                'line' => $row->business_line,
                'place' => trim(implode(', ', array_filter([$row->city, $row->state])), ', '),
                'provider' => $row->service_provider,
            ])
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Título humano de un corte, para el encabezado del detalle.
     */
    public static function dimensionLabel(string $dimension, string $value): string
    {
        return match ($dimension) {
            'service_status' => 'Estatus: '.$value,
            'service_type' => 'Tipo de servicio: '.$value,
            'business_line' => 'Línea de negocio: '.$value,
            'state' => 'Estado: '.$value,
            'specific_service' => 'Servicio: '.$value,
            'patient' => 'Paciente',
            'age_band' => 'Pacientes de '.$value.' años',
            'slot' => self::slotLabel($value),
            'all' => 'Todos los servicios',
            default => 'Detalle',
        };
    }

    /**
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return ['service_status', 'service_type', 'business_line', 'state', 'specific_service', 'patient', 'age_band', 'slot', 'all'];
    }

    public static function flush(): void
    {
        /** Con la caché `database`, increment() sobre una clave que no existe no la crea. */
        Cache::forever('ops-dashboard:version', (int) Cache::get('ops-dashboard:version', 0) + 1);
    }

    /**
     * @return Builder<OperationServiceStatistic>
     */
    public static function inPeriod(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return OperationsDashboardMetrics::statisticsQuery()
            ->whereRaw(self::DATE_SQL.' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return array<string, int|float>
     */
    private static function kpiValues(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $open = "'".implode("','", self::OPEN_STATUSES)."'";

        $row = self::inPeriod($from, $to)
            ->selectRaw('COUNT(*) as services')
            ->selectRaw("SUM(CASE WHEN UPPER(TRIM(service_status)) = 'FINALIZADO' THEN 1 ELSE 0 END) as finished")
            ->selectRaw('SUM(CASE WHEN UPPER(TRIM(service_status)) IN ('.$open.') THEN 1 ELSE 0 END) as open_services')
            ->selectRaw('COUNT(DISTINCT '.OperationsDashboardMetrics::patientKeySql().') as patients')
            ->selectRaw('COUNT(DISTINCT telemedicine_case_id) as cases')
            ->toBase()
            ->first();

        $services = (int) ($row->services ?? 0);
        $finished = (int) ($row->finished ?? 0);

        return [
            'services' => $services,
            'finished' => $finished,
            'open' => (int) ($row->open_services ?? 0),
            'close_rate' => $services > 0 ? round($finished * 100 / $services, 1) : 0.0,
            'patients' => (int) ($row->patients ?? 0),
            'cases' => (int) ($row->cases ?? 0),
        ];
    }

    /**
     * @return list<int>
     */
    private static function dailySeries(CarbonImmutable $from, CarbonImmutable $to, ?string $status, ?string $distinct = null): array
    {
        $query = self::inPeriod($from, $to)
            ->selectRaw('DATE('.self::DATE_SQL.') as day')
            ->selectRaw($distinct === null ? 'COUNT(*) as total' : 'COUNT(DISTINCT '.$distinct.') as total')
            ->groupByRaw('DATE('.self::DATE_SQL.')');

        if ($status !== null) {
            $query->whereRaw('UPPER(TRIM(service_status)) = ?', [$status]);
        }

        $rows = $query->pluck('total', 'day');
        $series = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $series[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return $series;
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private static function bucketed(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $daily = self::dailySeries($from, $to, null);
        $labels = [];
        $values = [];

        foreach ($daily as $index => $count) {
            $date = $from->addDays($index);
            [$key, $label] = match ($granularity) {
                OperationsDashboardPeriod::GRANULARITY_WEEK => [$date->startOfWeek()->toDateString(), 'Sem. '.$date->startOfWeek()->day.' '.OperationsDashboardPeriod::monthShort($date->startOfWeek()->month)],
                OperationsDashboardPeriod::GRANULARITY_MONTH => [$date->format('Y-m'), ucfirst(OperationsDashboardPeriod::monthShort($date->month)).' '.$date->format('y')],
                default => [$date->toDateString(), $date->day.' '.OperationsDashboardPeriod::monthShort($date->month)],
            };

            if (! array_key_exists($key, $labels)) {
                $labels[$key] = $label;
                $values[$key] = 0;
            }

            $values[$key] += $count;
        }

        return ['labels' => array_values($labels), 'values' => array_values($values)];
    }

    /**
     * @param  list<string>  $order
     * @return array<string, int>
     */
    private static function grouped(OperationsDashboardPeriod $period, string $column, string $emptyLabel, array $order = []): array
    {
        $expression = "COALESCE(NULLIF(UPPER(TRIM({$column})), ''), '".strtoupper($emptyLabel)."')";

        $counts = self::inPeriod($period->from, $period->to)
            ->selectRaw($expression.' as grouping_key, COUNT(*) as total')
            ->groupByRaw($expression)
            ->pluck('total', 'grouping_key')
            ->mapWithKeys(static fn (mixed $total, mixed $key): array => [
                ((string) $key === strtoupper($emptyLabel) ? $emptyLabel : (string) $key) => (int) $total,
            ])
            ->all();

        $ordered = [];

        foreach ($order as $label) {
            if (array_key_exists($label, $counts)) {
                $ordered[$label] = $counts[$label];
                unset($counts[$label]);
            }
        }

        arsort($counts);

        return $ordered + $counts;
    }

    /**
     * @param  array<string, int>  $counts  ya ordenado de mayor a menor
     * @return array{rows: list<array{label: string, value: int}>, others: int, total: int}
     */
    private static function ranked(array $counts, int $limit): array
    {
        $rows = [];

        foreach (array_slice($counts, 0, $limit, true) as $label => $value) {
            $rows[] = ['label' => (string) $label, 'value' => (int) $value];
        }

        return [
            'rows' => $rows,
            'others' => array_sum(array_slice($counts, $limit, null, true)),
            'total' => array_sum($counts),
        ];
    }

    /**
     * @param  Builder<OperationServiceStatistic>  $query
     */
    private static function applyDimension(Builder $query, string $dimension, string $value): bool
    {
        $value = trim($value);

        switch ($dimension) {
            case 'service_status':
            case 'service_type':
            case 'business_line':
            case 'state':
                str_starts_with($value, 'Sin ')
                    ? $query->where(fn (Builder $q) => $q->whereNull($dimension)->orWhereRaw("TRIM({$dimension}) = ''"))
                    : $query->whereRaw("UPPER(TRIM({$dimension})) = ?", [mb_strtoupper($value)]);

                return true;
            case 'specific_service':
                $query->whereRaw("COALESCE(NULLIF(TRIM(specific_service), ''), NULLIF(TRIM(service), ''), 'Sin servicio') = ?", [$value]);

                return true;
            case 'patient':
                $query->whereRaw(OperationsDashboardMetrics::patientKeySql().' = ?', [$value]);

                return true;
            case 'age_band':
                if (! isset(self::AGE_BANDS[$value])) {
                    return false;
                }

                $query->whereBetween('patient_age', self::AGE_BANDS[$value]);

                return true;
            case 'slot':
                [$weekday, $hour] = array_map('intval', explode('-', $value.'-'));

                if ($weekday < 0 || $weekday > 6 || $hour < 0 || $hour > 23) {
                    return false;
                }

                $query->whereRaw('WEEKDAY('.self::DATE_SQL.') = ?', [$weekday])->whereRaw('HOUR(started_at_time) = ?', [$hour]);

                return true;
            case 'all':
                return true;
            default:
                return false;
        }
    }

    private static function ageBandSql(): string
    {
        $sql = 'CASE';

        foreach (self::AGE_BANDS as $label => [$from, $to]) {
            $sql .= " WHEN patient_age BETWEEN {$from} AND {$to} THEN '{$label}'";
        }

        return $sql.' END';
    }

    private static function slotLabel(string $value): string
    {
        [$weekday, $hour] = array_map('intval', explode('-', $value.'-'));

        return (self::WEEKDAYS[$weekday] ?? '—').' de '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00 a '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':59';
    }

    private static function formatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private static function remember(string $metric, OperationsDashboardPeriod $period, Closure $callback): mixed
    {
        $user = Auth::user();
        $scope = (OperationsSupplierScope::currentSupplierId() ?? 'all')
            .'_'.(in_array('ATENMEDI', is_array($user?->departament) ? $user->departament : [], true) ? 'atenmedi' : 'tdg');
        $version = (int) Cache::get('ops-dashboard:version', 0);

        return Cache::remember(
            'ops-dashboard:'.$version.':'.$scope.':'.$metric.':'.$period->cacheKey(),
            self::CACHE_SECONDS,
            $callback,
        );
    }
}
