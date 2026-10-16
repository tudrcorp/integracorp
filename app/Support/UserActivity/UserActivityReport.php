<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\User;
use App\Models\UserActivityDay;
use App\Models\UserActivityEvent;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\UserActivity\Workday\WorkBreaks;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkdayEvaluator;
use App\Support\UserActivity\Workday\WorkDayKind;
use App\Support\UserActivity\Workday\WorkDayPlan;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reporte de Actividad de usuarios por rango de fechas y el detalle de una
 * persona. Lee los resúmenes diarios (una fila por usuario y día), así que un
 * rango de un año cuesta una consulta agregada, no millones de eventos.
 */
final class UserActivityReport
{
    /** Rango máximo del reporte, igual a la retención del resumen diario. */
    public const MAX_RANGE_DAYS = 366;

    /** @var list<string> */
    public const SORTABLE = ['name', 'days', 'online', 'active', 'idle', 'background', 'usage', 'avg_active', 'actions', 'pages', 'avg_first', 'avg_last', 'work_base', 'work_active', 'work_usage', 'work_absent', 'lunch_excess', 'overtime', 'guard'];

    /** @var array<string, string> Cifra del reporte => columna de `user_activity_days`. */
    private const WORKDAY_SUMS = [
        'work_base' => 'work_base_minutes',
        'work_active' => 'work_active_minutes',
        'work_idle' => 'work_idle_minutes',
        'work_absent' => 'work_absent_minutes',
        'lunch' => 'lunch_minutes',
        'lunch_excess' => 'lunch_excess_minutes',
        'overtime' => 'overtime_minutes',
        'guard' => 'guard_minutes',
        'guard_present' => 'guard_present_minutes',
    ];

    /**
     * Normaliza el rango: fechas válidas, en orden, sin pasar de hoy ni del máximo.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(?string $from, ?string $to): array
    {
        $today = UserActivityClock::now()->startOfDay();
        $parse = static function (?string $value): ?CarbonImmutable {
            try {
                return filled($value) ? CarbonImmutable::createFromFormat('Y-m-d', (string) $value, UserActivityClock::timezone())->startOfDay() : null;
            } catch (Throwable) {
                return null;
            }
        };

        $end = $parse($to) ?? $today;
        $start = $parse($from) ?? $end->startOfWeek();

        if ($end->greaterThan($today)) {
            $end = $today;
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) >= self::MAX_RANGE_DAYS) {
            $start = $end->subDays(self::MAX_RANGE_DAYS - 1);
        }

        return [$start, $end];
    }

    /**
     * @param  array{type?: string, search?: string, sort?: string, direction?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $aggregates = UserActivityDay::query()
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('user_id')
            ->select('user_id')
            ->selectRaw('COUNT(*) as days')
            ->selectRaw('SUM(online_minutes) as online')
            ->selectRaw('SUM(active_minutes) as active')
            ->selectRaw('SUM(idle_minutes) as idle')
            ->selectRaw('SUM(background_minutes) as background')
            ->selectRaw('SUM(page_views) as pages')
            ->selectRaw('SUM(actions) as actions')
            ->selectRaw('SUM(downloads) as downloads')
            ->selectRaw('AVG(first_minute) as avg_first')
            ->selectRaw('AVG(last_minute) as avg_last')
            ->selectRaw('MAX(activity_date) as last_day')
            ->when(WorkdayConfig::enabled(), static function ($query): void {
                $query->selectRaw('SUM(CASE WHEN workday_kind IS NOT NULL THEN 1 ELSE 0 END) as workday_days');

                foreach (self::WORKDAY_SUMS as $alias => $column) {
                    $query->selectRaw('SUM('.$column.') as '.$alias);
                }
            })
            ->toBase()
            ->get();

        $users = $aggregates->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $aggregates->pluck('user_id')->all())->get(UserActivityProfile::COLUMNS)->keyBy('id');

        $type = (string) ($filters['type'] ?? 'all');
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $rows = [];

        foreach ($aggregates as $aggregate) {
            $user = $users->get((int) $aggregate->user_id);
            $profile = UserActivityProfile::classify($user);
            $name = (string) ($user?->name ?? 'Usuario #'.$aggregate->user_id);
            $email = (string) ($user?->email ?? '');

            if ($type !== 'all' && $profile['type'] !== $type) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($name.' '.$email.' '.$profile['detail']), $search)) {
                continue;
            }

            $days = (int) $aggregate->days;
            $online = (int) $aggregate->online;
            $active = (int) $aggregate->active;

            $rows[] = [
                'user_id' => (int) $aggregate->user_id,
                'name' => $name,
                'email' => $email,
                'type' => $profile['type'],
                'type_label' => $profile['label'],
                'type_detail' => $profile['detail'],
                'days' => $days,
                'online' => $online,
                'active' => $active,
                'idle' => (int) $aggregate->idle,
                'background' => (int) $aggregate->background,
                'usage' => UserActivityMinutes::usagePercent($active, $online),
                'avg_active' => $days > 0 ? (int) round($active / $days) : 0,
                'pages' => (int) $aggregate->pages,
                'actions' => (int) $aggregate->actions,
                'downloads' => (int) $aggregate->downloads,
                'avg_first' => $aggregate->avg_first !== null ? (int) round((float) $aggregate->avg_first) : null,
                'avg_last' => $aggregate->avg_last !== null ? (int) round((float) $aggregate->avg_last) : null,
                'last_day' => (string) $aggregate->last_day,
                ...self::workdayFigures($aggregate),
            ];
        }

        $sort = in_array($filters['sort'] ?? '', self::SORTABLE, true) ? (string) $filters['sort'] : 'active';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 1 : -1;

        usort($rows, static function (array $a, array $b) use ($sort, $direction): int {
            $left = $sort === 'name' ? mb_strtolower($a['name']) : ($a[$sort] ?? -1);
            $right = $sort === 'name' ? mb_strtolower($b['name']) : ($b[$sort] ?? -1);

            return ($left <=> $right) * $direction ?: strcmp($a['name'], $b['name']);
        });

        $totals = [
            'users' => count($rows),
            'online' => array_sum(array_column($rows, 'online')),
            'active' => array_sum(array_column($rows, 'active')),
            'idle' => array_sum(array_column($rows, 'idle')),
            'background' => array_sum(array_column($rows, 'background')),
            'actions' => array_sum(array_column($rows, 'actions')),
            'pages' => array_sum(array_column($rows, 'pages')),
        ];
        $totals['usage'] = UserActivityMinutes::usagePercent($totals['active'], $totals['online']);

        foreach (array_keys(self::WORKDAY_SUMS) as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        $totals['workday_days'] = array_sum(array_column($rows, 'workday_days'));
        $totals['work_usage'] = WorkdayEvaluator::usage($totals['work_active'], $totals['work_base']);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Cifras de jornada de un agregado (rango por persona). Los días del régimen
     * anterior suman cero y no cuentan en `workday_days`.
     *
     * @return array<string, int|null>
     */
    private static function workdayFigures(object $aggregate): array
    {
        $figures = ['workday_days' => (int) ($aggregate->workday_days ?? 0)];

        foreach (array_keys(self::WORKDAY_SUMS) as $key) {
            $figures[$key] = (int) ($aggregate->{$key} ?? 0);
        }

        $figures['work_usage'] = WorkdayEvaluator::usage($figures['work_active'], $figures['work_base']);
        $figures['guard_presence'] = $figures['guard'] > 0 ? (int) round($figures['guard_present'] * 100 / $figures['guard']) : null;

        return $figures;
    }

    /**
     * Detalle de una persona en el rango: un renglón por día, mapa de calor
     * día de la semana × hora y lo que más usó.
     *
     * @return array{days: list<array<string, mixed>>, heatmap: array<int, list<int>>, heat_max: int, top_pages: list<array{label: string, total: int}>, top_panels: list<array{label: string, total: int}>}
     */
    public static function person(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $records = UserActivityDay::query()
            ->where('user_id', $userId)
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('activity_date')
            ->get();

        /** Lunes = 0 … domingo = 6. */
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        $days = [];
        $workday = self::workdayContext($userId, $from, $to, $records->contains(static fn (UserActivityDay $record): bool => $record->workday_kind !== null));

        foreach ($records as $record) {
            $weekday = ((int) $record->activity_date->dayOfWeekIso) - 1;

            foreach (array_values((array) ($record->hourly_active ?? [])) as $hour => $minutes) {
                if ($hour < 24) {
                    $heatmap[$weekday][$hour] += (int) $minutes;
                }
            }

            $days[] = [
                'date' => $record->activity_date->toDateString(),
                'online' => $record->online_minutes,
                'active' => $record->active_minutes,
                'idle' => $record->idle_minutes,
                'background' => $record->background_minutes,
                'usage' => UserActivityMinutes::usagePercent($record->active_minutes, $record->online_minutes),
                'first' => $record->first_minute,
                'last' => $record->last_minute,
                'actions' => $record->actions,
                'pages' => $record->page_views,
                ...self::recordWorkday($record, $workday),
            ];
        }

        $heatMax = max(1, ...array_map('max', $heatmap));

        $eventsFrom = $from->format('Y-m-d H:i:s');
        $eventsTo = $to->endOfDay()->format('Y-m-d H:i:s');

        $topPages = UserActivityEvent::query()
            ->where('user_id', $userId)
            ->where('type', 'page')
            ->whereBetween('occurred_at', [$eventsFrom, $eventsTo])
            ->groupBy('label')
            ->select('label', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')
            ->limit(8)
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->all();

        $topPanels = UserActivityEvent::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['page', 'action'])
            ->whereNotNull('panel')
            ->whereBetween('occurred_at', [$eventsFrom, $eventsTo])
            ->groupBy('panel')
            ->select('panel', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')
            ->limit(6)
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => ['label' => (string) $row->panel, 'total' => (int) $row->total])
            ->all();

        return ['days' => $days, 'heatmap' => $heatmap, 'heat_max' => $heatMax, 'top_pages' => $topPages, 'top_panels' => $topPanels];
    }

    /**
     * Un día de una persona: barra minuto a minuto, cifras y recorrido.
     * Hoy sale de Redis/caché (al segundo); los días anteriores, de la base.
     *
     * @return array{date: string, is_today: bool, summary: array<string, mixed>, segments: list<array<string, mixed>>, events: list<array<string, mixed>>, detail_available: bool}
     */
    public static function day(int $userId, CarbonImmutable $date, int $eventLimit = 400): array
    {
        $date = $date->setTimezone(UserActivityClock::timezone())->startOfDay();
        $isToday = $date->isSameDay(UserActivityClock::now());
        $minutes = [];
        $pending = [];

        if ($isToday) {
            try {
                $store = LivePresenceStore::repository();
                $minutes = $store->activityMinutes($userId, UserActivityClock::dayKey($date));
                $pending = array_values(array_filter(
                    $store->peekActivityEvents(5000),
                    static fn (array $event): bool => (int) ($event['user_id'] ?? 0) === $userId,
                ));
            } catch (Throwable) {
                $minutes = [];
            }
        }

        $record = UserActivityDay::query()->where('user_id', $userId)->whereDate('activity_date', $date->toDateString())->first();

        if ($minutes === [] && $record !== null) {
            $minutes = UserActivityMinutes::fromStates($record->minute_states);
        }

        $summary = UserActivityMinutes::summarize($minutes);
        $detailAvailable = $minutes !== [] || $record === null || $record->minute_states !== null;
        $workday = self::dayWorkday($userId, $date, $minutes, $record, $isToday);

        /** Pasados los 90 días la barra ya no existe: quedan las cifras del resumen. */
        if ($minutes === [] && $record !== null) {
            $summary = [
                ...$summary,
                'online' => $record->online_minutes,
                'active' => $record->active_minutes,
                'idle' => $record->idle_minutes,
                'background' => $record->background_minutes,
                'first' => $record->first_minute,
                'last' => $record->last_minute,
            ];
        }

        $stored = UserActivityEvent::query()
            ->where('user_id', $userId)
            ->whereBetween('occurred_at', [$date->format('Y-m-d H:i:s'), $date->endOfDay()->format('Y-m-d H:i:s')])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($eventLimit)
            ->get(['event_key', 'occurred_at', 'type', 'label', 'panel', 'page', 'duration_ms', 'status'])
            ->map(static fn (UserActivityEvent $event): array => [
                'key' => $event->event_key,
                'at' => $event->occurred_at->format('Y-m-d H:i:s'),
                'type' => $event->type,
                'label' => $event->label,
                'panel' => $event->panel,
                'page' => $event->page,
                'ms' => $event->duration_ms,
                'status' => $event->status,
            ])
            ->all();

        $storedKeys = array_flip(array_column($stored, 'key'));
        $fresh = [];

        foreach ($pending as $event) {
            $key = (string) ($event['event_key'] ?? '');

            if ($key === '' || isset($storedKeys[$key]) || ! str_starts_with((string) ($event['occurred_at'] ?? ''), $date->toDateString())) {
                continue;
            }

            $fresh[] = [
                'key' => $key,
                'at' => (string) $event['occurred_at'],
                'type' => (string) ($event['type'] ?? 'page'),
                'label' => (string) ($event['label'] ?? ''),
                'panel' => $event['panel'] ?? null,
                'page' => $event['page'] ?? null,
                'ms' => $event['duration_ms'] ?? null,
                'status' => $event['status'] ?? null,
            ];
        }

        $events = [...$fresh, ...$stored];
        usort($events, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return [
            'date' => $date->toDateString(),
            'is_today' => $isToday,
            'summary' => [
                ...$summary,
                'usage' => UserActivityMinutes::usagePercent((int) $summary['active'], (int) $summary['online']),
                'actions' => $record?->actions ?? count(array_filter($events, static fn (array $event): bool => $event['type'] === 'action')),
                'pages' => $record?->page_views ?? count(array_filter($events, static fn (array $event): bool => $event['type'] === 'page')),
            ],
            'segments' => $workday['segments'] ?? UserActivityMinutes::segments($minutes),
            'bands' => $workday['bands'] ?? [],
            'workday' => $workday['summary'] ?? null,
            'plan' => $workday['plan'] ?? null,
            'events' => array_slice($events, 0, $eventLimit),
            'detail_available' => $detailAvailable,
        ];
    }

    /**
     * Jornada de un día de una persona para el detalle. Un día cerrado toma las
     * cifras guardadas (incluyen la parte de una guardia que terminó al día
     * siguiente); hoy se calcula al minuto.
     *
     * @param  array<int, string>  $minutes
     * @return array{summary: array<string, mixed>, segments: list<array<string, mixed>>, bands: list<array<string, mixed>>, plan: WorkDayPlan}|null
     */
    private static function dayWorkday(int $userId, CarbonImmutable $date, array $minutes, ?UserActivityDay $record, bool $isToday): ?array
    {
        if (! WorkdayConfig::appliesTo($date) || ($record !== null && $record->workday_kind === null && ! $isToday)) {
            return null;
        }

        $user = User::query()->find($userId, WorkdaySubjects::columns());

        if (! WorkdaySubjects::isSubject($user)) {
            return null;
        }

        $plan = WorkDayPlanner::forDay($user, $date);
        $lunch = WorkBreaks::forUsers([$userId], $date, $date)[$userId][$date->toDateString()] ?? null;
        $result = WorkdayEvaluator::evaluate($plan, $minutes, $lunch);

        if ($record !== null && $record->workday_kind !== null && ! $isToday) {
            $result['summary'] = [...$result['summary'], ...self::storedWorkday($record)];
        }

        return [...$result, 'plan' => $plan];
    }

    /**
     * Contexto para pintar varios días de una persona: su jornada y almuerzos del rango, en lote.
     *
     * @return array{plans: array<string, WorkDayPlan>, lunches: array<string, array{start: int, end: int|null, reason: string|null}>}|null
     */
    private static function workdayContext(int $userId, CarbonImmutable $from, CarbonImmutable $to, bool $hasWorkdayDays): ?array
    {
        if (! $hasWorkdayDays || ! WorkdayConfig::enabled()) {
            return null;
        }

        $user = User::query()->find($userId, WorkdaySubjects::columns());

        if ($user === null) {
            return null;
        }

        return [
            'plans' => WorkDayPlanner::plans(collect([$user->id => $user]), $from, $to)[$user->id] ?? [],
            'lunches' => WorkBreaks::forUsers([$userId], $from, $to)[$userId] ?? [],
        ];
    }

    /**
     * Barra y cifras de jornada de un día guardado.
     *
     * @param  array{plans: array<string, WorkDayPlan>, lunches: array<string, array{start: int, end: int|null, reason: string|null}>}|null  $context
     * @return array<string, mixed>
     */
    private static function recordWorkday(UserActivityDay $record, ?array $context): array
    {
        $minutes = UserActivityMinutes::fromStates($record->minute_states);
        $date = $record->activity_date->toDateString();
        $plan = $context['plans'][$date] ?? null;

        if ($record->workday_kind === null || $plan === null) {
            return ['segments' => UserActivityMinutes::segments($minutes), 'bands' => [], 'workday' => null];
        }

        $result = WorkdayEvaluator::evaluate($plan, $minutes, $context['lunches'][$date] ?? null);

        return [
            'segments' => $record->minute_states === null ? [] : $result['segments'],
            'bands' => $result['bands'],
            'workday' => [...$result['summary'], ...self::storedWorkday($record)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function storedWorkday(UserActivityDay $record): array
    {
        $base = (int) $record->work_base_minutes;
        $guard = (int) $record->guard_minutes;

        return [
            'kind' => WorkDayKind::tryFrom((string) $record->workday_kind) ?? WorkDayKind::Workday,
            'base' => $base,
            'work_active' => (int) $record->work_active_minutes,
            'work_idle' => (int) $record->work_idle_minutes,
            'work_absent' => (int) $record->work_absent_minutes,
            'lunch' => (int) $record->lunch_minutes,
            'lunch_excess' => (int) $record->lunch_excess_minutes,
            'overtime' => (int) $record->overtime_minutes,
            'guard' => $guard,
            'guard_present' => (int) $record->guard_present_minutes,
            'guard_active' => (int) $record->guard_active_minutes,
            'usage' => WorkdayEvaluator::usage((int) $record->work_active_minutes, $base),
            'guard_presence' => $guard > 0 ? (int) round((int) $record->guard_present_minutes * 100 / $guard) : null,
        ];
    }
}
