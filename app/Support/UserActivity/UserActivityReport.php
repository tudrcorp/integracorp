<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\User;
use App\Models\UserActivityDay;
use App\Models\UserActivityEvent;
use App\Support\LivePresence\LivePresenceStore;
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
    public const SORTABLE = ['name', 'days', 'online', 'active', 'idle', 'background', 'usage', 'avg_active', 'actions', 'pages', 'avg_first', 'avg_last'];

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

        return ['rows' => $rows, 'totals' => $totals];
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
                'segments' => UserActivityMinutes::segments(UserActivityMinutes::fromStates($record->minute_states)),
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
            'segments' => UserActivityMinutes::segments($minutes),
            'events' => array_slice($events, 0, $eventLimit),
            'detail_available' => $detailAvailable,
        ];
    }
}
