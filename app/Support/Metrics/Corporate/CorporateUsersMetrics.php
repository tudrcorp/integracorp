<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityProfile;
use App\Support\UserActivity\Workday\WorkdayEvaluator;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Usuarios: cuánto, cuándo y con qué resultado usa cada persona el sistema.
 *
 * Fuentes:
 * - Uso: `user_activity_days` (un resumen por persona y día; existe desde el
 *   {@see self::MEASURED_SINCE}). Activo = interactuó en los últimos 5 min.
 * - Jornada: las columnas `work_*` del mismo resumen (desde el 16/10/2026),
 *   que ya descuentan almuerzo declarado, reuniones y ausencias.
 * - Productividad: registros creados en el período. Las tablas del negocio
 *   guardan al autor por **nombre** (`created_by`), así que se cruzan por
 *   nombre normalizado; las consultas de telemedicina, por el médico.
 *
 * Dos poblaciones que no se mezclan en los promedios: el **personal** (internos
 * y médicos, los que tienen jornada) y la **red comercial** (agentes, agencias y
 * proveedores que entran a sus portales).
 */
final class CorporateUsersMetrics
{
    public const STAFF = 'staff';

    public const NETWORK = 'network';

    /** Primer día con medición de actividad. */
    public const MEASURED_SINCE = '2026-10-08';

    /** Días sin conexión a partir de los cuales una cuenta activa se considera sin uso. */
    public const IDLE_ACCOUNT_DAYS = 30;

    /** Uso de la jornada (%) por debajo del cual una persona pide atención. */
    public const LOW_WORK_USAGE = 50;

    /** Jornada mínima (minutos) en el período para juzgar el uso de alguien. */
    public const MIN_WORK_BASE = 480;

    /** Uso de la jornada (%) que se considera bueno para la empresa. */
    public const GOOD_WORK_USAGE = 80;

    /** Un área por debajo de esta fracción del promedio de la empresa se avisa. */
    public const LOW_AREA_RATIO = 0.6;

    /** Horas activas sin un solo registro creado que se mencionan en la lectura. */
    public const ACTIVE_WITHOUT_RECORDS_MINUTES = 600;

    /** @var array<string, string> Fuente de productividad => texto. */
    public const SOURCES = [
        'affiliations' => 'Afiliaciones individuales',
        'corporate_affiliations' => 'Afiliaciones corporativas',
        'quotes' => 'Cotizaciones',
        'coordinations' => 'Coordinaciones de servicio',
        'orders' => 'Órdenes de servicio',
        'consultations' => 'Consultas de telemedicina',
    ];

    /** @var array<string, array{0: string, 1: string}> Fuente => [tabla, columna del autor por nombre]. */
    private const NAMED_SOURCES = [
        'affiliations' => ['affiliations', 'created_by'],
        'corporate_affiliations' => ['affiliation_corporates', 'created_by'],
        'quotes' => ['individual_quotes', 'created_by'],
        'quotes_corporate' => ['corporate_quotes', 'created_by'],
        'coordinations' => ['operation_coordination_services', 'created_by'],
        'orders' => ['operation_service_orders', 'created_by'],
    ];

    /** @var array<string, string> Id de panel => nombre que ve la directiva. */
    public const PANELS = [
        'admin' => 'Administrador',
        'business' => 'Negocios',
        'operations' => 'Operaciones',
        'administration' => 'Administración',
        'marketing' => 'Marketing',
        'agents' => 'Agentes',
        'master' => 'Agencia master',
        'general' => 'Agencia general',
        'telemedicina' => 'Telemedicina',
        'projects' => 'Proyectos',
        'metrics' => 'Métricas',
    ];

    /** @var array<string, string> Departamento guardado (sin tildes) => nombre del área. */
    public const DEPARTMENTS = [
        'NEGOCIOS' => 'Negocios',
        'OPERACIONES' => 'Operaciones',
        'ADMINISTRACION' => 'Administración',
        'MARKETING' => 'Marketing',
        'TELEMEDICINA' => 'Telemedicina',
        'PROYECTOS' => 'Proyectos',
        'METRICAS' => 'Métricas',
    ];

    /** @var array<string, string> Tipo de usuario => texto en plural. */
    public const TYPE_PLURALS = [
        'internal' => 'Internos',
        'doctor' => 'Médicos',
        'agent' => 'Agentes',
        'agency' => 'Agencias',
        'provider' => 'Proveedores',
        'other' => 'Otros',
    ];

    /**
     * Todas las cuentas del sistema clasificadas (foto a hoy).
     *
     * @return array<int, array{name: string, email: string, status: string, active: bool, type: string, type_label: string, segment: string, department: string, created: ?string, doctor_id: ?int}>
     */
    public static function directory(): array
    {
        return CorporateMetrics::remember('users_directory', null, static function (): array {
            $subjects = WorkdaySubjects::all()->keys()->flip();
            $directory = [];

            User::query()
                ->select([...UserActivityProfile::COLUMNS, 'doctor_id', 'created_at'])
                ->orderBy('id')
                ->chunk(500, static function ($users) use ($subjects, &$directory): void {
                    foreach ($users as $user) {
                        $profile = UserActivityProfile::classify($user);
                        $isStaff = $subjects->has($user->id) || in_array($profile['type'], ['internal', 'doctor'], true);

                        $directory[(int) $user->id] = [
                            'name' => trim((string) $user->name) !== '' ? trim((string) $user->name) : 'Usuario #'.$user->id,
                            'email' => (string) $user->email,
                            'status' => mb_strtoupper(trim((string) $user->status)),
                            'active' => mb_strtoupper(trim((string) $user->status)) === 'ACTIVO',
                            'type' => $profile['type'],
                            'type_label' => $profile['label'],
                            'segment' => $isStaff ? self::STAFF : self::NETWORK,
                            'department' => self::department($user, $profile['type'], $isStaff),
                            'created' => $user->created_at?->toDateString(),
                            'doctor_id' => $user->doctor_id !== null ? (int) $user->doctor_id : null,
                        ];
                    }
                });

            return $directory;
        });
    }

    /**
     * Personas del segmento en el período: uso, jornada y productividad.
     * El personal activo aparece aunque no se haya conectado (cuenta en cero);
     * la red comercial, solo si se conectó.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function people(Period $period, string $segment = self::STAFF, bool $previous = false): array
    {
        $from = $previous ? $period->previousFrom : $period->from;
        $to = $previous ? $period->previousTo : $period->to;

        return CorporateMetrics::remember('users_people_'.$segment.($previous ? '_prev' : ''), $period, static fn (): array => self::build($from, $to, $segment));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function build(CarbonImmutable $from, CarbonImmutable $to, string $segment): array
    {
        $directory = self::directory();
        $records = self::productivity($from, $to);
        $aggregates = self::days($from, $to)
            ->groupBy('user_id')
            ->select('user_id')
            ->selectRaw('SUM(CASE WHEN active_minutes > 0 THEN 1 ELSE 0 END) as days')
            ->selectRaw('SUM(online_minutes) as online')
            ->selectRaw('SUM(active_minutes) as active')
            ->selectRaw('SUM(idle_minutes) as idle')
            ->selectRaw('SUM(background_minutes) as background')
            ->selectRaw('SUM(actions) as actions')
            ->selectRaw('SUM(page_views) as pages')
            ->selectRaw('AVG(CASE WHEN active_minutes > 0 THEN first_minute END) as avg_first')
            ->selectRaw('AVG(CASE WHEN active_minutes > 0 THEN last_minute END) as avg_last')
            ->selectRaw('SUM(work_base_minutes) as work_base')
            ->selectRaw('SUM(work_active_minutes) as work_active')
            ->selectRaw('SUM(work_meeting_minutes) as work_meeting')
            ->selectRaw('SUM(work_absent_minutes) as work_absent')
            ->selectRaw('SUM(overtime_minutes) as overtime')
            ->selectRaw('SUM(lunch_excess_minutes) as lunch_excess')
            ->selectRaw('SUM(CASE WHEN workday_kind IS NOT NULL AND work_base_minutes > 0 AND online_minutes = 0 THEN 1 ELSE 0 END) as absent_days')
            ->get()
            ->keyBy('user_id');

        $ids = $aggregates->keys()->map(static fn (mixed $id): int => (int) $id)->all();

        if ($segment === self::STAFF) {
            foreach ($directory as $id => $entry) {
                if ($entry['segment'] === self::STAFF && $entry['active'] && ($entry['created'] === null || $entry['created'] <= $to->toDateString())) {
                    $ids[] = $id;
                }
            }
        }

        $rows = [];

        foreach (array_unique($ids) as $id) {
            $entry = $directory[$id] ?? null;

            if ($entry === null || $entry['segment'] !== $segment) {
                continue;
            }

            $aggregate = $aggregates->get($id);
            $days = (int) ($aggregate->days ?? 0);
            $active = (int) ($aggregate->active ?? 0);
            $workBase = (int) ($aggregate->work_base ?? 0);
            $workActive = (int) ($aggregate->work_active ?? 0) + (int) ($aggregate->work_meeting ?? 0);
            $sources = $records[$id] ?? [];
            $total = array_sum($sources);

            $rows[$id] = [
                'user_id' => $id,
                'name' => $entry['name'],
                'email' => $entry['email'],
                'type' => $entry['type'],
                'type_label' => $entry['type_label'],
                'department' => $entry['department'],
                'days' => $days,
                'online' => (int) ($aggregate->online ?? 0),
                'active' => $active,
                'idle' => (int) ($aggregate->idle ?? 0),
                'background' => (int) ($aggregate->background ?? 0),
                'actions' => (int) ($aggregate->actions ?? 0),
                'pages' => (int) ($aggregate->pages ?? 0),
                'avg_day' => $days > 0 ? (int) round($active / $days) : 0,
                'avg_first' => isset($aggregate->avg_first) ? (int) round((float) $aggregate->avg_first) : null,
                'avg_last' => isset($aggregate->avg_last) ? (int) round((float) $aggregate->avg_last) : null,
                'work_base' => $workBase,
                'work_active' => $workActive,
                'work_usage' => WorkdayEvaluator::usage($workActive, $workBase),
                'work_absent' => (int) ($aggregate->work_absent ?? 0),
                'absent_days' => (int) ($aggregate->absent_days ?? 0),
                'overtime' => (int) ($aggregate->overtime ?? 0),
                'lunch_excess' => (int) ($aggregate->lunch_excess ?? 0),
                'records' => $total,
                'sources' => $sources,
                'records_per_hour' => $active > 0 ? round($total * 60 / $active, 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * Cifras del personal en el período y en el anterior, con tendencias.
     *
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<float>>, snapshot: array<string, int>}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('users_kpis', $period, static fn (): array => [
            'current' => self::summarize(self::people($period)),
            'previous' => self::summarize(self::people($period, previous: true)),
            'sparklines' => [
                'hours' => self::dailyHours($period->from, $period->to),
                'people' => self::dailyPeople($period->from, $period->to),
            ],
            'snapshot' => self::snapshot(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @return array{people: int, hours: float, avg_day_hours: float, work_usage: float|null, overtime_hours: float, records: int, accounts: int}
     */
    public static function summarize(array $people): array
    {
        $connected = array_filter($people, static fn (array $row): bool => $row['active'] > 0);
        $active = array_sum(array_column($people, 'active'));
        $personDays = array_sum(array_column($people, 'days'));
        $workBase = array_sum(array_column($people, 'work_base'));

        return [
            'people' => count($connected),
            'hours' => round($active / 60, 1),
            'avg_day_hours' => $personDays > 0 ? round($active / $personDays / 60, 1) : 0.0,
            'work_usage' => $workBase > 0 ? (float) WorkdayEvaluator::usage(array_sum(array_column($people, 'work_active')), $workBase) : null,
            'overtime_hours' => round(array_sum(array_column($people, 'overtime')) / 60, 1),
            'records' => array_sum(array_column($people, 'records')),
            'accounts' => count($people),
        ];
    }

    /**
     * Cuentas a hoy: activas por segmento, conectadas hoy y sin uso reciente.
     *
     * @return array{accounts: int, staff: int, network: int, online_today: int, idle_accounts: int}
     */
    public static function snapshot(): array
    {
        $directory = self::directory();
        $active = array_filter($directory, static fn (array $entry): bool => $entry['active']);
        $today = UserActivityClock::now()->toDateString();

        return [
            'accounts' => count($active),
            'staff' => count(array_filter($active, static fn (array $entry): bool => $entry['segment'] === self::STAFF)),
            'network' => count(array_filter($active, static fn (array $entry): bool => $entry['segment'] === self::NETWORK)),
            'online_today' => (int) DB::table('user_activity_days')->where('activity_date', $today)->where('online_minutes', '>', 0)->count(),
            'idle_accounts' => count(self::idleAccounts()),
        ];
    }

    /**
     * Personal con cuenta activa que no entra al sistema hace
     * {@see self::IDLE_ACCOUNT_DAYS} días o más (o nunca), del más antiguo al
     * más reciente. La última conexión es el último día con actividad o el
     * último inicio de sesión registrado, lo que sea más reciente.
     *
     * @return list<array{user_id: int, name: string, department: string, last_seen: ?string, days: ?int}>
     */
    public static function idleAccounts(): array
    {
        return CorporateMetrics::remember('users_idle_accounts', null, static function (): array {
            $today = UserActivityClock::now()->startOfDay();
            $lastSeen = self::lastSeen();
            $out = [];

            foreach (self::directory() as $id => $entry) {
                if (! $entry['active'] || $entry['segment'] !== self::STAFF) {
                    continue;
                }

                $seen = $lastSeen[$id] ?? null;
                $days = $seen !== null ? (int) CarbonImmutable::parse($seen)->startOfDay()->diffInDays($today) : null;

                if ($seen === null && $entry['created'] !== null && CarbonImmutable::parse($entry['created'])->diffInDays($today) < self::IDLE_ACCOUNT_DAYS) {
                    continue;
                }

                if ($days === null || $days >= self::IDLE_ACCOUNT_DAYS) {
                    $out[] = ['user_id' => $id, 'name' => $entry['name'], 'department' => $entry['department'], 'last_seen' => $seen, 'days' => $days];
                }
            }

            usort($out, static fn (array $a, array $b): int => ($b['days'] ?? PHP_INT_MAX) <=> ($a['days'] ?? PHP_INT_MAX) ?: strcmp($a['name'], $b['name']));

            return $out;
        });
    }

    /**
     * Última conexión conocida por persona (fecha Y-m-d).
     *
     * @return array<int, string>
     */
    public static function lastSeen(): array
    {
        return CorporateMetrics::remember('users_last_seen', null, static function (): array {
            $seen = DB::table('user_activity_days')
                ->where('online_minutes', '>', 0)
                ->groupBy('user_id')
                ->select('user_id')
                ->selectRaw('MAX(activity_date) as last_day')
                ->pluck('last_day', 'user_id')
                ->map(static fn (mixed $day): string => substr((string) $day, 0, 10))
                ->all();

            $logins = DB::table('logs')
                ->where('action', 'AUDIT_USER_SESSION_LOGIN')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->select('user_id')
                ->selectRaw('MAX(created_at) as last_login')
                ->pluck('last_login', 'user_id');

            foreach ($logins as $id => $login) {
                $day = substr((string) $login, 0, 10);
                $seen[(int) $id] = max($seen[(int) $id] ?? '', $day);
            }

            return $seen;
        });
    }

    /**
     * Horas activas del personal por día (o semana/mes), con el período anterior.
     *
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, previous_labels: list<string>}
     */
    public static function hoursTrend(Period $period): array
    {
        return CorporateMetrics::remember('users_hours_trend', $period, static fn (): array => CorporateMetrics::trend($period, static fn (CarbonImmutable $from, CarbonImmutable $to): array => self::dailyHours($from, $to)));
    }

    /**
     * Las personas con más horas activas del período.
     *
     * @return array{rows: list<array{label: string, value: float, key: string, meta: string}>, others: float, total: float}
     */
    public static function topActive(Period $period, string $segment = self::STAFF, int $limit = 10): array
    {
        $people = array_filter(self::people($period, $segment), static fn (array $row): bool => $row['active'] > 0);
        usort($people, static fn (array $a, array $b): int => $b['active'] <=> $a['active']);

        return self::ranking($people, $limit, static fn (array $row): float => round($row['active'] / 60, 1), static fn (array $row): string => ($segment === self::STAFF ? $row['department'] : $row['type_label']).' · '.self::hours($row['avg_day']).' por día');
    }

    /**
     * El personal que menos usa el sistema (incluye a quien no se conectó).
     *
     * @return array{rows: list<array{label: string, value: float, key: string, meta: string}>, others: float, total: float}
     */
    public static function leastActive(Period $period, int $limit = 10): array
    {
        $people = self::people($period);
        usort($people, static fn (array $a, array $b): int => $a['active'] <=> $b['active'] ?: strcmp($a['name'], $b['name']));

        $ranking = self::ranking($people, $limit, static fn (array $row): float => round($row['active'] / 60, 1), static fn (array $row): string => $row['department'].' · '.($row['active'] === 0 ? 'sin conexión' : $row['days'].' '.($row['days'] === 1 ? 'día' : 'días')));

        /** «Otros» serían las personas que más usan el sistema: aquí no aporta. */
        return ['rows' => $ranking['rows'], 'others' => 0, 'total' => $ranking['total']];
    }

    /**
     * Las personas con más registros creados en el período.
     *
     * @return array{rows: list<array{label: string, value: int, key: string, meta: string}>, others: int, total: int}
     */
    public static function topProductive(Period $period, int $limit = 10): array
    {
        $people = array_filter(self::people($period), static fn (array $row): bool => $row['records'] > 0);
        usort($people, static fn (array $a, array $b): int => $b['records'] <=> $a['records']);

        return self::ranking($people, $limit, static fn (array $row): int => (int) $row['records'], static function (array $row): string {
            $sources = $row['sources'];
            arsort($sources);

            return $row['department'].' · '.mb_strtolower(self::SOURCES[(string) array_key_first($sources)] ?? 'registros');
        });
    }

    /**
     * Promedio de horas activas por persona y día conectado, por área.
     *
     * @return array<string, float>
     */
    public static function avgHoursByDepartment(Period $period): array
    {
        $out = [];

        foreach (self::byDepartment(self::people($period)) as $department => $rows) {
            $days = array_sum(array_column($rows, 'days'));

            if ($days > 0) {
                $out[$department] = round(array_sum(array_column($rows, 'active')) / $days / 60, 1);
            }
        }

        arsort($out);

        return $out;
    }

    /**
     * Registros creados en el período, por área.
     *
     * @return array<string, int>
     */
    public static function recordsByDepartment(Period $period): array
    {
        $out = [];

        foreach (self::byDepartment(self::people($period)) as $department => $rows) {
            $total = array_sum(array_column($rows, 'records'));

            if ($total > 0) {
                $out[$department] = $total;
            }
        }

        arsort($out);

        return $out;
    }

    /**
     * En qué se va el tiempo con el sistema abierto (horas del personal).
     *
     * @return array<string, float>
     */
    public static function timeComposition(Period $period): array
    {
        $people = self::people($period);

        return array_filter([
            'Activo' => round(array_sum(array_column($people, 'active')) / 60, 1),
            'Inactivo' => round(array_sum(array_column($people, 'idle')) / 60, 1),
            'En otra pestaña' => round(array_sum(array_column($people, 'background')) / 60, 1),
        ], static fn (float $hours): bool => $hours > 0);
    }

    /**
     * Minutos activos del personal por día de la semana (lunes = 0) y hora.
     *
     * @return array{matrix: list<list<int>>, max: int, total: int}
     */
    public static function heatmap(Period $period): array
    {
        return CorporateMetrics::remember('users_heatmap', $period, static function () use ($period): array {
            $matrix = array_fill(0, 7, array_fill(0, 24, 0));

            self::days($period->from, $period->to)
                ->whereIn('user_id', self::segmentIds(self::STAFF))
                ->where('active_minutes', '>', 0)
                ->whereNotNull('hourly_active')
                ->select(['id', 'activity_date', 'hourly_active'])
                ->orderBy('id')
                ->chunk(2000, static function ($rows) use (&$matrix): void {
                    foreach ($rows as $row) {
                        $hours = json_decode((string) $row->hourly_active, true);

                        if (! is_array($hours)) {
                            continue;
                        }

                        $weekday = CarbonImmutable::parse(substr((string) $row->activity_date, 0, 10))->dayOfWeekIso - 1;

                        foreach (array_values($hours) as $hour => $minutes) {
                            if ($hour < 24) {
                                $matrix[$weekday][$hour] += (int) $minutes;
                            }
                        }
                    }
                });

            $flat = array_merge(...$matrix);

            return ['matrix' => $matrix, 'max' => max($flat) ?: 0, 'total' => array_sum($flat)];
        });
    }

    /**
     * Pantallas abiertas y acciones del personal por panel (el recorrido
     * paso a paso se conserva 90 días).
     *
     * @return array<string, int>
     */
    public static function panels(Period $period): array
    {
        return CorporateMetrics::remember('users_panels', $period, static function () use ($period): array {
            $rows = DB::table('user_activity_events')
                ->whereIn('user_id', self::segmentIds(self::STAFF))
                ->whereIn('type', ['page', 'action'])
                ->whereNotNull('panel')
                ->whereBetween('occurred_at', CorporateMetrics::range($period->from, $period->to))
                ->groupBy('panel')
                ->select('panel')
                ->selectRaw('COUNT(*) as total')
                ->pluck('total', 'panel');

            $out = [];

            foreach ($rows as $panel => $total) {
                $label = self::PANELS[(string) $panel] ?? ucfirst((string) $panel);
                $out[$label] = ($out[$label] ?? 0) + (int) $total;
            }

            arsort($out);

            return $out;
        });
    }

    /**
     * Comparativa del personal: cada persona frente al promedio de su área y de
     * la empresa, con su variación contra el período anterior.
     *
     * @return array{rows: list<array<string, mixed>>, company_avg_day: int, company_records_per_hour: float|null}
     */
    public static function comparison(Period $period): array
    {
        return CorporateMetrics::remember('users_comparison', $period, static function () use ($period): array {
            $people = self::people($period);
            $previous = self::people($period, previous: true);
            $lastSeen = self::lastSeen();
            $areaAvg = [];

            foreach (self::byDepartment($people) as $department => $rows) {
                $days = array_sum(array_column($rows, 'days'));
                $areaAvg[$department] = $days > 0 ? array_sum(array_column($rows, 'active')) / $days : 0.0;
            }

            $companyDays = array_sum(array_column($people, 'days'));
            $companyActive = array_sum(array_column($people, 'active'));
            $companyAvg = $companyDays > 0 ? $companyActive / $companyDays : 0.0;
            $rows = [];

            foreach ($people as $id => $row) {
                $area = $areaAvg[$row['department']] ?? 0.0;
                $before = $previous[$id]['active'] ?? 0;

                $rows[] = $row + [
                    'vs_area' => $row['days'] > 0 && $area > 0 ? round(($row['avg_day'] - $area) * 100 / $area) : null,
                    'vs_company' => $row['days'] > 0 && $companyAvg > 0 ? round(($row['avg_day'] - $companyAvg) * 100 / $companyAvg) : null,
                    'change' => $period->compare ? CorporateMetrics::change((float) $row['active'], (float) $before) : null,
                    'last_seen' => $lastSeen[$id] ?? null,
                ];
            }

            usort($rows, static fn (array $a, array $b): int => $b['active'] <=> $a['active'] ?: strcmp($a['name'], $b['name']));

            return [
                'rows' => $rows,
                'company_avg_day' => (int) round($companyAvg),
                'company_records_per_hour' => $companyActive > 0 ? round(array_sum(array_column($people, 'records')) * 60 / $companyActive, 1) : null,
            ];
        });
    }

    /**
     * Red comercial conectada en el período, por tipo de cuenta.
     *
     * @return array<string, int>
     */
    public static function networkByType(Period $period): array
    {
        $out = [];

        foreach (self::people($period, self::NETWORK) as $row) {
            if ($row['active'] > 0) {
                $label = self::TYPE_PLURALS[$row['type']] ?? $row['type_label'];
                $out[$label] = ($out[$label] ?? 0) + 1;
            }
        }

        arsort($out);

        return $out;
    }

    /**
     * Cuentas activas del sistema por tipo (foto a hoy).
     *
     * @return array<string, int>
     */
    public static function accountsByType(): array
    {
        $out = [];

        foreach (self::directory() as $entry) {
            if ($entry['active']) {
                $label = self::TYPE_PLURALS[$entry['type']] ?? $entry['type_label'];
                $out[$label] = ($out[$label] ?? 0) + 1;
            }
        }

        arsort($out);

        return $out;
    }

    /**
     * Lectura automática del período sobre las personas. Reglas fijas y
     * explicables; los umbrales son las constantes de esta clase.
     *
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    public static function insights(Period $period): array
    {
        return CorporateMetrics::remember('users_insights', $period, static function () use ($period): array {
            $out = [];
            $people = self::people($period);
            $summary = self::summarize($people);

            $since = CarbonImmutable::parse(self::MEASURED_SINCE)->format('d/m/Y');

            if ($period->from->toDateString() < self::MEASURED_SINCE) {
                $out[] = self::insight(CorporateInsights::NEUTRAL, 'La medición empezó el '.$since, 'Los días anteriores no tienen registro de actividad: las cifras de esas fechas salen en cero, no porque nadie trabajara.');
            } elseif ($period->compare && $period->previousFrom->toDateString() < self::MEASURED_SINCE) {
                $out[] = self::insight(CorporateInsights::NEUTRAL, 'El período anterior es previo a la medición', 'La actividad se mide desde el '.$since.': la comparación de uso y jornada contra '.$period->previousLabel().' no es representativa. Los registros creados sí se comparan.');
            }

            if ($summary['work_usage'] !== null) {
                $usage = (float) $summary['work_usage'];
                $tone = $usage >= self::GOOD_WORK_USAGE ? CorporateInsights::GOOD : ($usage < 60 ? CorporateInsights::WARNING : CorporateInsights::NEUTRAL);
                $out[] = self::insight($tone, 'Uso de la jornada: '.CorporateMetrics::number($usage, 1).' %', 'Tiempo activo y en reuniones dentro del horario sobre la jornada esperada del personal, sin el almuerzo declarado.');
            }

            $low = array_values(array_filter($people, static fn (array $row): bool => $row['work_base'] >= self::MIN_WORK_BASE && $row['work_usage'] !== null && $row['work_usage'] < self::LOW_WORK_USAGE));

            if ($low !== []) {
                usort($low, static fn (array $a, array $b): int => $a['work_usage'] <=> $b['work_usage']);
                $out[] = self::insight(CorporateInsights::WARNING, self::count(count($low), 'persona usa', 'personas usan').' menos del '.self::LOW_WORK_USAGE.' % de su jornada', self::names($low, static fn (array $row): string => $row['name'].' ('.$row['work_usage'].' %)').'.');
            }

            $idle = self::idleAccounts();

            if ($idle !== []) {
                $out[] = self::insight(CorporateInsights::WARNING, self::count(count($idle), 'cuenta interna activa', 'cuentas internas activas').' sin uso en '.self::IDLE_ACCOUNT_DAYS.' días o más', self::names($idle, static fn (array $row): string => $row['name']).'. Conviene revisar si siguen necesitando acceso.');
            }

            $areas = self::avgHoursByDepartment($period);
            $companyAvg = $summary['avg_day_hours'];

            if (count($areas) > 1 && $companyAvg > 0) {
                $lowest = array_key_last($areas);

                if ($areas[$lowest] < $companyAvg * self::LOW_AREA_RATIO) {
                    $out[] = self::insight(CorporateInsights::WARNING, $lowest.': el área con menos uso diario', self::hours((int) round($areas[$lowest] * 60)).' activas por persona y día, frente a '.self::hours((int) round($companyAvg * 60)).' del promedio de la empresa.');
                }
            }

            $overtime = array_filter($people, static fn (array $row): bool => $row['overtime'] > 0);

            if ($overtime !== []) {
                usort($overtime, static fn (array $a, array $b): int => $b['overtime'] <=> $a['overtime']);
                $total = array_sum(array_column($overtime, 'overtime'));
                $out[] = self::insight(CorporateInsights::NEUTRAL, self::hours($total).' de horas extra en el período', self::names($overtime, static fn (array $row): string => $row['name'].' ('.self::hours($row['overtime']).')', 3).' concentran la mayor parte.');
            }

            $productive = array_filter($people, static fn (array $row): bool => $row['records'] > 0);

            if ($productive !== []) {
                usort($productive, static fn (array $a, array $b): int => $b['records'] <=> $a['records']);
                $top = $productive[0];
                $out[] = self::insight(CorporateInsights::GOOD, $top['name'].' encabeza la productividad', CorporateMetrics::number($top['records']).' registros creados en el período ('.$top['department'].'). En total, el personal creó '.CorporateMetrics::number($summary['records']).'.');
            }

            $silent = array_values(array_filter($people, static fn (array $row): bool => $row['active'] >= self::ACTIVE_WITHOUT_RECORDS_MINUTES && $row['records'] === 0));

            if ($silent !== []) {
                $out[] = self::insight(CorporateInsights::NEUTRAL, self::count(count($silent), 'persona', 'personas').' con más de '.intdiv(self::ACTIVE_WITHOUT_RECORDS_MINUTES, 60).' h activas sin registros creados', self::names($silent, static fn (array $row): string => $row['name']).'. Puede ser normal en su rol (consulta, análisis, administración); vale confirmar en qué se usa ese tiempo.');
            }

            $order = [CorporateInsights::CRITICAL => 0, CorporateInsights::WARNING => 1, CorporateInsights::GOOD => 2, CorporateInsights::NEUTRAL => 3];
            usort($out, static fn (array $a, array $b): int => $order[$a['tone']] <=> $order[$b['tone']]);

            return $out;
        });
    }

    /**
     * Detalle de una persona: un renglón por día del período.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function personDays(Period $period, int $userId, int $limit): array
    {
        $records = self::productivityByDay($period->from, $period->to, $userId);
        $query = self::days($period->from, $period->to)->where('user_id', $userId)->where('online_minutes', '>', 0);
        $total = (clone $query)->count();

        $rows = $query->orderByDesc('activity_date')->limit($limit)->get()->map(static function (object $row) use ($records): array {
            $date = substr((string) $row->activity_date, 0, 10);
            $usage = WorkdayEvaluator::usage((int) $row->work_active_minutes + (int) $row->work_meeting_minutes, (int) $row->work_base_minutes);

            return [
                'id' => 'ud'.$row->id,
                'date' => CarbonImmutable::parse($date)->format('d/m/Y'),
                'first' => $row->first_minute !== null ? UserActivityClock::formatMinute((int) $row->first_minute) : null,
                'last' => $row->last_minute !== null ? UserActivityClock::formatMinute((int) $row->last_minute) : null,
                'active' => self::hours((int) $row->active_minutes),
                'idle' => self::hours((int) $row->idle_minutes),
                'work_usage' => $usage !== null ? $usage.' %' : 'Sin jornada',
                'overtime' => (int) $row->overtime_minutes > 0 ? self::hours((int) $row->overtime_minutes) : null,
                'records' => CorporateMetrics::number($records[$date] ?? 0),
            ];
        })->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Detalle de un área: sus personas en el período.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function departmentPeople(Period $period, string $department, int $limit): array
    {
        $rows = array_values(array_filter(self::people($period), static fn (array $row): bool => $row['department'] === $department));
        usort($rows, static fn (array $a, array $b): int => $b['active'] <=> $a['active']);

        return [
            'rows' => array_map(static fn (array $row): array => [
                'id' => 'up'.$row['user_id'],
                'name' => $row['name'],
                'days' => CorporateMetrics::number($row['days']),
                'active' => self::hours($row['active']),
                'avg_day' => self::hours($row['avg_day']),
                'work_usage' => $row['work_usage'] !== null ? $row['work_usage'].' %' : 'Sin jornada',
                'overtime' => $row['overtime'] > 0 ? self::hours($row['overtime']) : null,
                'records' => CorporateMetrics::number($row['records']),
            ], array_slice($rows, 0, $limit)),
            'total' => count($rows),
        ];
    }

    /**
     * Minutos como «3 h 25 min» (o «45 min»).
     */
    public static function hours(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest.' min';
        }

        return CorporateMetrics::number($hours).' h'.($rest > 0 ? ' '.$rest.' min' : '');
    }

    /**
     * Registros creados por persona y fuente en el rango.
     *
     * @return array<int, array<string, int>>
     */
    public static function productivity(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $names = self::nameIndex();
        $out = [];

        foreach (self::NAMED_SOURCES as $source => [$table, $column]) {
            $rows = DB::table($table)
                ->whereBetween($table.'.created_at', CorporateMetrics::range($from, $to))
                ->whereNotNull($table.'.'.$column)
                ->groupBy($table.'.'.$column)
                ->select($table.'.'.$column.' as author')
                ->selectRaw('COUNT(*) as total')
                ->get();
            $key = $source === 'quotes_corporate' ? 'quotes' : $source;

            foreach ($rows as $row) {
                $id = $names[self::normalizeName((string) $row->author)] ?? null;

                if ($id !== null) {
                    $out[$id][$key] = ($out[$id][$key] ?? 0) + (int) $row->total;
                }
            }
        }

        $doctors = self::doctorIndex();
        $consultations = DB::table('telemedicine_consultation_patients')
            ->whereBetween('telemedicine_consultation_patients.created_at', CorporateMetrics::range($from, $to))
            ->whereNotNull('telemedicine_consultation_patients.telemedicine_doctor_id')
            ->groupBy('telemedicine_consultation_patients.telemedicine_doctor_id')
            ->select('telemedicine_consultation_patients.telemedicine_doctor_id as doctor')
            ->selectRaw('COUNT(*) as total')
            ->get();

        foreach ($consultations as $row) {
            $id = $doctors[(int) $row->doctor] ?? null;

            if ($id !== null) {
                $out[$id]['consultations'] = ($out[$id]['consultations'] ?? 0) + (int) $row->total;
            }
        }

        return $out;
    }

    /**
     * Nombre normalizado => usuario. Si dos cuentas comparten nombre gana la
     * del personal activo; si no, la activa; si no, la más antigua.
     *
     * @return array<string, int>
     */
    public static function nameIndex(): array
    {
        $index = [];
        $rank = [];

        foreach (self::directory() as $id => $entry) {
            $name = self::normalizeName($entry['name']);

            if ($name === '' || str_starts_with($entry['name'], 'Usuario #')) {
                continue;
            }

            $score = ($entry['segment'] === self::STAFF && $entry['active'] ? 2 : 0) + ($entry['active'] ? 1 : 0);

            if (! isset($index[$name]) || $score > $rank[$name]) {
                $index[$name] = $id;
                $rank[$name] = $score;
            }
        }

        return $index;
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
    }

    /**
     * Id de cada usuario por segmento.
     *
     * @return list<int>
     */
    public static function segmentIds(string $segment): array
    {
        return array_keys(array_filter(self::directory(), static fn (array $entry): bool => $entry['segment'] === $segment)) ?: [0];
    }

    private static function department(User $user, string $type, bool $isStaff): string
    {
        if (! $isStaff) {
            return UserActivityProfile::TYPES[$type] ?? 'Otro';
        }

        $department = collect(CorporatePaymentFrequencyChangeRecipients::departmentsOf($user))
            ->reject(static fn (string $value): bool => $value === 'SUPERADMIN')
            ->first();

        if (filled($department)) {
            return self::DEPARTMENTS[(string) $department] ?? mb_convert_case((string) $department, MB_CASE_TITLE, 'UTF-8');
        }

        return $type === 'doctor' || (bool) $user->is_doctor ? 'Médicos' : 'Sin área';
    }

    private static function days(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('user_activity_days')->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return list<float>
     */
    private static function dailyHours(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::days($from, $to)->whereIn('user_id', self::segmentIds(self::STAFF)), 'user_activity_days.activity_date', $from, $to, 'user_activity_days.active_minutes / 60');
    }

    /**
     * @return list<float>
     */
    private static function dailyPeople(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::days($from, $to)->whereIn('user_id', self::segmentIds(self::STAFF))->where('active_minutes', '>', 0), 'user_activity_days.activity_date', $from, $to);
    }

    /**
     * Registros creados por una persona, por día (Y-m-d => total).
     *
     * @return array<string, int>
     */
    private static function productivityByDay(CarbonImmutable $from, CarbonImmutable $to, int $userId): array
    {
        $entry = self::directory()[$userId] ?? null;

        if ($entry === null) {
            return [];
        }

        $names = array_keys(array_filter(self::nameIndex(), static fn (int $id): bool => $id === $userId));
        $out = [];

        $add = static function (Builder $query, string $dateColumn) use (&$out, $from, $to): void {
            $rows = $query->whereBetween($dateColumn, CorporateMetrics::range($from, $to))->pluck($dateColumn);

            foreach ($rows as $date) {
                $day = substr((string) $date, 0, 10);
                $out[$day] = ($out[$day] ?? 0) + 1;
            }
        };

        if ($names !== []) {
            foreach (self::NAMED_SOURCES as [$table, $column]) {
                /** El autor se guarda como texto libre: se buscan sus variantes tal como están escritas. */
                $authors = DB::table($table)
                    ->whereBetween($table.'.created_at', CorporateMetrics::range($from, $to))
                    ->whereNotNull($table.'.'.$column)
                    ->distinct()
                    ->pluck($table.'.'.$column)
                    ->filter(static fn (mixed $author): bool => in_array(self::normalizeName((string) $author), $names, true))
                    ->values()
                    ->all();

                if ($authors !== []) {
                    $add(DB::table($table)->whereIn($table.'.'.$column, $authors), $table.'.created_at');
                }
            }
        }

        $doctorIds = array_keys(array_filter(self::doctorIndex(), static fn (int $id): bool => $id === $userId));

        if ($doctorIds !== []) {
            $add(DB::table('telemedicine_consultation_patients')->whereIn('telemedicine_consultation_patients.telemedicine_doctor_id', $doctorIds), 'telemedicine_consultation_patients.created_at');
        }

        return $out;
    }

    /**
     * Médico de telemedicina => usuario (la cuenta activa si hay varias).
     *
     * @return array<int, int>
     */
    private static function doctorIndex(): array
    {
        $index = [];

        foreach (self::directory() as $id => $entry) {
            if ($entry['doctor_id'] !== null && (! isset($index[$entry['doctor_id']]) || $entry['active'])) {
                $index[$entry['doctor_id']] = $id;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @return array<string, list<array<string, mixed>>>
     */
    private static function byDepartment(array $people): array
    {
        $out = [];

        foreach ($people as $row) {
            $out[$row['department']][] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $sorted
     * @param  callable(array<string, mixed>): (int|float)  $value
     * @param  callable(array<string, mixed>): string  $meta
     * @return array{rows: list<array{label: string, value: int|float, key: string, meta: string}>, others: int|float, total: int|float}
     */
    private static function ranking(array $sorted, int $limit, callable $value, callable $meta): array
    {
        $values = array_map($value, $sorted);
        $rows = [];

        foreach (array_slice($sorted, 0, $limit) as $index => $row) {
            $rows[] = ['label' => $row['name'], 'value' => $values[$index], 'key' => (string) $row['user_id'], 'meta' => $meta($row)];
        }

        return [
            'rows' => $rows,
            'others' => array_sum(array_slice($values, $limit)),
            'total' => array_sum($values),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): string  $format
     */
    private static function names(array $rows, callable $format, int $limit = 4): string
    {
        $names = array_map($format, array_slice($rows, 0, $limit));
        $rest = count($rows) - count($names);

        return implode(', ', $names).($rest > 0 ? ' y '.self::count($rest, 'más', 'más') : '');
    }

    private static function count(int $value, string $singular, string $plural): string
    {
        return CorporateMetrics::number($value).' '.($value === 1 ? $singular : $plural);
    }

    /**
     * @return array{tone: string, area: string, title: string, text: string, url_key: string}
     */
    private static function insight(string $tone, string $title, string $text): array
    {
        return ['tone' => $tone, 'area' => 'Usuarios', 'title' => $title, 'text' => $text, 'url_key' => 'users'];
    }
}
