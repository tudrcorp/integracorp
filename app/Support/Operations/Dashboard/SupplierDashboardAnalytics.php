<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Operations\SupplierDashboardMetrics;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del Dashboard del Proveedor: sus casos de telemedicina y las consultas de
 * esos casos, en el período elegido.
 *
 * Todo se acota al proveedor del usuario en sesión
 * ({@see SupplierDashboardMetrics::supplierId()}); sin proveedor, todo vuelve
 * vacío. Los casos eliminados quedan fuera por el scope global del caso.
 */
final class SupplierDashboardAnalytics
{
    public const CACHE_SECONDS = 60;

    public const DISCHARGE_STATUS = 'ALTA MEDICA';

    public const STATUS_ORDER = ['ASIGNADO', 'EN SEGUIMIENTO', 'ALTA MEDICA'];

    public const SEX_LABELS = ['Femenino', 'Masculino', 'Sin dato'];

    /**
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<int>>}
     */
    public static function kpis(OperationsDashboardPeriod $period): array
    {
        return self::remember('kpis', $period, static fn (): array => [
            'current' => self::kpiValues($period->from, $period->to),
            'previous' => self::kpiValues($period->previousFrom, $period->previousTo),
            'sparklines' => [
                'new_cases' => self::dailySeries(self::casesBetween($period->from, $period->to), $period),
                'consultations' => self::dailySeries(self::consultationsBetween($period->from, $period->to), $period),
                'discharges' => self::dailySeries(self::consultationsBetween($period->from, $period->to)->where('status', self::DISCHARGE_STATUS), $period, 'telemedicine_case_id'),
                'patients' => self::dailySeries(self::consultationsBetween($period->from, $period->to), $period, 'telemedicine_patient_id'),
                'doctors' => self::dailySeries(self::consultationsBetween($period->from, $period->to), $period, 'telemedicine_doctor_id'),
            ],
        ]);
    }

    /**
     * Consultas por día, semana o mes, con el período anterior alineado por posición.
     *
     * @return array{labels: list<string>, current: list<int>, previous: list<int>, previous_labels: list<string>}
     */
    public static function trend(OperationsDashboardPeriod $period): array
    {
        return self::remember('trend', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $current = self::bucketed(self::dailySeries(self::consultationsBetween($period->from, $period->to), null, null, $period->from, $period->to), $period->from, $granularity);
            $previous = self::bucketed(self::dailySeries(self::consultationsBetween($period->previousFrom, $period->previousTo), null, null, $period->previousFrom, $period->previousTo), $period->previousFrom, $granularity);
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
     * Casos abiertos en el período, por su estado actual.
     *
     * @return array<string, int>
     */
    public static function caseStatus(OperationsDashboardPeriod $period): array
    {
        return self::remember('case_status', $period, static function () use ($period): array {
            $counts = self::casesBetween($period->from, $period->to)
                ->selectRaw("COALESCE(NULLIF(UPPER(TRIM(status)), ''), 'SIN ESTADO') as grouping_key, COUNT(*) as total")
                ->groupBy('grouping_key')
                ->pluck('total', 'grouping_key')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all();

            return self::ordered($counts, self::STATUS_ORDER);
        });
    }

    /**
     * Casos distintos por tipo de servicio prestado en el período.
     *
     * @return array<string, int>
     */
    public static function serviceTypes(OperationsDashboardPeriod $period): array
    {
        return self::remember('service_types', $period, static function () use ($period): array {
            $counts = SupplierDashboardMetrics::casesByServiceTypeBetween($period->from, $period->to);
            arsort($counts);

            return $counts;
        });
    }

    /**
     * Médicos con más consultas registradas en el período.
     *
     * @return array{rows: list<array{label: string, value: int, key: string, meta: string}>, others: int, total: int}
     */
    public static function doctors(OperationsDashboardPeriod $period, int $limit = 8): array
    {
        return self::remember('doctors_'.$limit, $period, static function () use ($period, $limit): array {
            $rows = self::consultationsBetween($period->from, $period->to)
                ->leftJoin('telemedicine_doctors', 'telemedicine_doctors.id', '=', 'telemedicine_consultation_patients.telemedicine_doctor_id')
                ->selectRaw('telemedicine_consultation_patients.telemedicine_doctor_id as doctor_id')
                ->selectRaw('MAX(telemedicine_doctors.full_name) as full_name')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('COUNT(DISTINCT telemedicine_consultation_patients.telemedicine_case_id) as cases')
                ->groupBy('telemedicine_consultation_patients.telemedicine_doctor_id')
                ->orderByDesc('total')
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => filled($row->full_name) ? (string) $row->full_name : 'Sin médico',
                'value' => (int) $row->total,
                'key' => (string) ($row->doctor_id ?? 0),
                'meta' => ((int) $row->cases === 1 ? '1 caso' : (int) $row->cases.' casos'),
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => (int) $mapped->slice($limit)->sum('value'),
                'total' => (int) $mapped->sum('value'),
            ];
        });
    }

    /**
     * Casos abiertos en el período, por prioridad.
     *
     * @return array{rows: list<array{label: string, value: int}>, others: int, total: int}
     */
    public static function priorities(OperationsDashboardPeriod $period): array
    {
        return self::remember('priorities', $period, static function () use ($period): array {
            $counts = self::casesBetween($period->from, $period->to)
                ->leftJoin('telemedicine_priorities', 'telemedicine_priorities.id', '=', 'telemedicine_cases.telemedicine_priority_id')
                ->selectRaw("COALESCE(NULLIF(TRIM(telemedicine_priorities.name), ''), 'Sin prioridad') as grouping_key, COUNT(*) as total")
                ->groupBy('grouping_key')
                ->orderByDesc('total')
                ->pluck('total', 'grouping_key')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all();

            $rows = [];

            foreach ($counts as $label => $value) {
                $rows[] = ['label' => (string) $label, 'value' => $value];
            }

            return ['rows' => $rows, 'others' => 0, 'total' => array_sum($counts)];
        });
    }

    /**
     * Consultas por día de la semana (lunes primero) y hora.
     *
     * @return array{matrix: list<list<int>>, max: int, total: int}
     */
    public static function heatmap(OperationsDashboardPeriod $period): array
    {
        return self::remember('heatmap', $period, static function () use ($period): array {
            $rows = self::consultationsBetween($period->from, $period->to)
                ->selectRaw('WEEKDAY(telemedicine_consultation_patients.created_at) as grp_weekday')
                ->selectRaw('HOUR(telemedicine_consultation_patients.created_at) as grp_hour')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('grp_weekday', 'grp_hour')
                ->get();

            $matrix = array_fill(0, 7, array_fill(0, 24, 0));

            foreach ($rows as $row) {
                $matrix[(int) $row->grp_weekday][(int) $row->grp_hour] = (int) $row->total;
            }

            $flat = array_merge(...$matrix);

            return ['matrix' => $matrix, 'max' => max($flat ?: [0]), 'total' => array_sum($flat)];
        });
    }

    /**
     * Pacientes de los casos abiertos en el período: por edad y por sexo.
     *
     * @return array{ages: array<string, int>, sexes: array<string, int>, total: int}
     */
    public static function patients(OperationsDashboardPeriod $period): array
    {
        return self::remember('patients', $period, static function () use ($period): array {
            $ages = [];
            $ageSql = self::ageBandSql();
            $ageRows = self::casesBetween($period->from, $period->to)
                ->whereNotNull('patient_age')
                ->selectRaw($ageSql.' as grp_band, COUNT(DISTINCT telemedicine_patient_id) as total')
                ->groupBy('grp_band')
                ->pluck('total', 'grp_band');

            foreach (array_keys(OperationsDashboardAnalytics::AGE_BANDS) as $label) {
                $ages[$label] = (int) ($ageRows[$label] ?? 0);
            }

            $sexSql = self::sexSql();
            $sexRows = self::casesBetween($period->from, $period->to)
                ->selectRaw($sexSql.' as grp_sex, COUNT(DISTINCT telemedicine_patient_id) as total')
                ->groupBy('grp_sex')
                ->pluck('total', 'grp_sex');

            $sexes = [];

            foreach (self::SEX_LABELS as $label) {
                if ((int) ($sexRows[$label] ?? 0) > 0) {
                    $sexes[$label] = (int) $sexRows[$label];
                }
            }

            return [
                'ages' => $ages,
                'sexes' => $sexes,
                'total' => (int) self::casesBetween($period->from, $period->to)->distinct()->count('telemedicine_patient_id'),
            ];
        });
    }

    /**
     * Casos de un corte del dashboard (lo que se abre al hacer clic).
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function detail(OperationsDashboardPeriod $period, string $dimension, string $value, int $limit = 200): array
    {
        if (SupplierDashboardMetrics::supplierId() === null) {
            return ['rows' => [], 'total' => 0];
        }

        $query = self::casesForDimension($period, $dimension, trim($value));

        if ($query === null) {
            return ['rows' => [], 'total' => 0];
        }

        $total = (clone $query)->count();

        $rows = $query
            ->with(['priority:id,name', 'telemedicineDoctor:id,full_name'])
            ->withCount('consultations')
            ->orderByDesc('telemedicine_cases.created_at')
            ->limit($limit)
            ->get(['telemedicine_cases.id', 'telemedicine_cases.code', 'telemedicine_cases.created_at', 'telemedicine_cases.patient_name', 'telemedicine_cases.status', 'telemedicine_cases.telemedicine_priority_id', 'telemedicine_cases.telemedicine_doctor_id'])
            ->map(static fn (TelemedicineCase $case): array => [
                'id' => (int) $case->id,
                'case_id' => (int) $case->id,
                'case_code' => $case->code,
                'date' => $case->created_at?->format('d/m/Y'),
                'time' => $case->created_at?->format('H:i'),
                'patient' => $case->patient_name,
                'status' => $case->status,
                'priority' => $case->priority?->name,
                'doctor' => $case->telemedicineDoctor?->full_name,
                'consultations' => (int) $case->consultations_count,
            ])
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public static function dimensionLabel(string $dimension, string $value): string
    {
        return match ($dimension) {
            'case_status' => 'Estado: '.$value,
            'service_type' => 'Tipo de servicio: '.$value,
            'doctor' => 'Casos atendidos por el médico',
            'priority' => 'Prioridad: '.$value,
            'slot' => 'Consultas del tramo seleccionado',
            'age_band' => 'Pacientes de '.$value.' años',
            'sex' => 'Pacientes: '.$value,
            'all' => 'Todos los casos',
            default => 'Detalle',
        };
    }

    /**
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return ['case_status', 'service_type', 'doctor', 'priority', 'slot', 'age_band', 'sex', 'all'];
    }

    /**
     * Casos del proveedor abiertos en el rango.
     *
     * @return Builder<TelemedicineCase>
     */
    public static function casesBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return TelemedicineCase::query()
            ->whereIn('telemedicine_cases.id', SupplierDashboardMetrics::caseIdsQuery())
            ->whereBetween('telemedicine_cases.created_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * Consultas de los casos del proveedor registradas en el rango.
     *
     * @return Builder<TelemedicineConsultationPatient>
     */
    public static function consultationsBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return TelemedicineConsultationPatient::query()
            ->whereIn('telemedicine_consultation_patients.telemedicine_case_id', SupplierDashboardMetrics::caseIdsQuery())
            ->whereBetween('telemedicine_consultation_patients.created_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * @return array<string, int|float|null>
     */
    private static function kpiValues(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $consultations = self::consultationsBetween($from, $to)
            ->selectRaw('COUNT(*) as consultations')
            ->selectRaw('COUNT(DISTINCT telemedicine_patient_id) as patients')
            ->selectRaw('COUNT(DISTINCT telemedicine_doctor_id) as doctors')
            ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'ALTA MEDICA' THEN telemedicine_case_id END) as discharges")
            ->toBase()
            ->first();

        /** Días desde que se abrió el caso hasta su alta, para las altas del rango. */
        $days = self::consultationsBetween($from, $to)
            ->where('telemedicine_consultation_patients.status', self::DISCHARGE_STATUS)
            ->join('telemedicine_cases', 'telemedicine_cases.id', '=', 'telemedicine_consultation_patients.telemedicine_case_id')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, telemedicine_cases.created_at, telemedicine_consultation_patients.created_at)) / 24 as average_days')
            ->toBase()
            ->value('average_days');

        return [
            'new_cases' => self::casesBetween($from, $to)->count(),
            'consultations' => (int) ($consultations->consultations ?? 0),
            'discharges' => (int) ($consultations->discharges ?? 0),
            'average_days' => $days === null ? null : round((float) $days, 1),
            'patients' => (int) ($consultations->patients ?? 0),
            'doctors' => (int) ($consultations->doctors ?? 0),
        ];
    }

    /**
     * Serie diaria del rango (el del período si no se indica otro).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<int>
     */
    private static function dailySeries(Builder $query, ?OperationsDashboardPeriod $period, ?string $distinct = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= $period->from;
        $to ??= $period->to;
        $table = $query->getModel()->getTable();
        $date = 'DATE('.$table.'.created_at)';

        $rows = $query
            ->selectRaw($date.' as grp_day')
            ->selectRaw($distinct === null ? 'COUNT(*) as total' : 'COUNT(DISTINCT '.$table.'.'.$distinct.') as total')
            ->groupBy('grp_day')
            ->pluck('total', 'grp_day');

        $series = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $series[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return $series;
    }

    /**
     * @param  list<int>  $daily
     * @return array{labels: list<string>, values: list<int>}
     */
    private static function bucketed(array $daily, CarbonImmutable $from, string $granularity): array
    {
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
     * @return Builder<TelemedicineCase>|null
     */
    private static function casesForDimension(OperationsDashboardPeriod $period, string $dimension, string $value): ?Builder
    {
        $range = [$period->from->startOfDay(), $period->to->endOfDay()];

        return match ($dimension) {
            'case_status' => self::casesBetween($period->from, $period->to)
                ->whereRaw("COALESCE(NULLIF(UPPER(TRIM(status)), ''), 'SIN ESTADO') = ?", [mb_strtoupper($value)]),
            'priority' => self::casesBetween($period->from, $period->to)
                ->leftJoin('telemedicine_priorities', 'telemedicine_priorities.id', '=', 'telemedicine_cases.telemedicine_priority_id')
                ->whereRaw("COALESCE(NULLIF(TRIM(telemedicine_priorities.name), ''), 'Sin prioridad') = ?", [$value]),
            'age_band' => isset(OperationsDashboardAnalytics::AGE_BANDS[$value])
                ? self::casesBetween($period->from, $period->to)->whereBetween('patient_age', OperationsDashboardAnalytics::AGE_BANDS[$value])
                : null,
            'sex' => in_array($value, self::SEX_LABELS, true)
                ? self::casesBetween($period->from, $period->to)->whereRaw(self::sexSql().' = ?', [$value])
                : null,
            'service_type' => in_array($value, SupplierDashboardMetrics::SERVICE_TYPE_LABELS, true)
                ? self::scopedCases()->whereIn('telemedicine_cases.id', SupplierDashboardMetrics::caseIdsForServiceType($value, $range))
                : null,
            'doctor' => ctype_digit($value)
                ? self::scopedCases()->whereIn('telemedicine_cases.id', self::consultationsBetween($period->from, $period->to)
                    ->where('telemedicine_consultation_patients.telemedicine_doctor_id', (int) $value)
                    ->select('telemedicine_consultation_patients.telemedicine_case_id'))
                : null,
            'slot' => self::slotCases($period, $value),
            'all' => self::casesBetween($period->from, $period->to),
            default => null,
        };
    }

    /**
     * @return Builder<TelemedicineCase>|null
     */
    private static function slotCases(OperationsDashboardPeriod $period, string $value): ?Builder
    {
        [$weekday, $hour] = array_map('intval', explode('-', $value.'-'));

        if ($weekday < 0 || $weekday > 6 || $hour < 0 || $hour > 23 || ! preg_match('/^\d-\d{1,2}$/', $value)) {
            return null;
        }

        return self::scopedCases()->whereIn('telemedicine_cases.id', self::consultationsBetween($period->from, $period->to)
            ->whereRaw('WEEKDAY(telemedicine_consultation_patients.created_at) = ?', [$weekday])
            ->whereRaw('HOUR(telemedicine_consultation_patients.created_at) = ?', [$hour])
            ->select('telemedicine_consultation_patients.telemedicine_case_id'));
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    private static function scopedCases(): Builder
    {
        return TelemedicineCase::query()->whereIn('telemedicine_cases.id', SupplierDashboardMetrics::caseIdsQuery());
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $order
     * @return array<string, int>
     */
    private static function ordered(array $counts, array $order): array
    {
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

    private static function ageBandSql(): string
    {
        $sql = 'CASE';

        foreach (OperationsDashboardAnalytics::AGE_BANDS as $label => [$from, $to]) {
            $sql .= " WHEN patient_age BETWEEN {$from} AND {$to} THEN '{$label}'";
        }

        return $sql.' END';
    }

    private static function sexSql(): string
    {
        return "CASE
            WHEN UPPER(TRIM(COALESCE(patient_sex, ''))) IN ('F', 'FEMENINO', 'FEMENINA') THEN 'Femenino'
            WHEN UPPER(TRIM(COALESCE(patient_sex, ''))) IN ('M', 'MASCULINO') THEN 'Masculino'
            ELSE 'Sin dato'
        END";
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private static function remember(string $metric, OperationsDashboardPeriod $period, Closure $callback): mixed
    {
        $supplierId = SupplierDashboardMetrics::supplierId();

        if ($supplierId === null) {
            return $callback();
        }

        $version = (int) Cache::get('ops-dashboard:version', 0);

        return Cache::remember(
            'supplier-dashboard:'.$version.':'.$supplierId.':'.$metric.':'.$period->cacheKey(),
            self::CACHE_SECONDS,
            $callback,
        );
    }
}
