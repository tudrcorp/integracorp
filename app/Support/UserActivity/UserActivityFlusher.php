<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\UserActivityDay;
use App\Models\UserActivityEvent;
use App\Support\LivePresence\LivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pasa a MySQL lo que se acumuló en Redis/caché:
 *
 * 1. La cola de eventos del recorrido → `user_activity_events` (idempotente por
 *    `event_key`: reintentar no duplica).
 * 2. Los minutos de hoy (y de ayer, recién pasada la medianoche) → una fila por
 *    usuario en `user_activity_days`. Se recalcula el día completo desde los
 *    minutos, no se suma: correrlo dos veces deja el mismo resultado.
 */
final class UserActivityFlusher
{
    /** Tope de lotes por corrida: si la cola creció mucho, el resto va en la siguiente. */
    private const MAX_BATCHES = 20;

    /**
     * @return array{events: int, days: int}
     */
    public static function flush(?CarbonImmutable $now = null, ?LivePresenceRepository $store = null): array
    {
        $now ??= UserActivityClock::now();
        $store ??= LivePresenceStore::repository();

        $events = self::flushEvents($store);
        $days = 0;

        foreach (self::daysToFlush($now) as $day) {
            $days += self::flushDay($store, $day);
        }

        return ['events' => $events, 'days' => $days];
    }

    public static function flushEvents(LivePresenceRepository $store): int
    {
        $batch = max(100, (int) config('live-presence.activity.flush_batch', 1000));
        $saved = 0;

        for ($round = 0; $round < self::MAX_BATCHES; $round++) {
            $items = $store->takeActivityEvents($batch);

            if ($items === []) {
                break;
            }

            $rows = array_values(array_filter(array_map(self::eventRow(...), $items)));

            try {
                foreach (array_chunk($rows, 500) as $chunk) {
                    UserActivityEvent::query()->insertOrIgnore($chunk);
                }
            } catch (Throwable $exception) {
                /** Devolverlos a la cola: el siguiente minuto se reintenta sin perder el recorrido. */
                $store->appendActivityEvents($items, max(3600, (int) config('live-presence.activity.buffer_ttl', 259200)));

                throw $exception;
            }

            $saved += count($rows);

            if (count($items) < $batch) {
                break;
            }
        }

        return $saved;
    }

    /**
     * @param  CarbonImmutable  $day  Cualquier momento del día a volcar.
     */
    public static function flushDay(LivePresenceRepository $store, CarbonImmutable $day): int
    {
        $dayKey = UserActivityClock::dayKey($day);
        $userIds = $store->activityUsers($dayKey);

        if ($userIds === []) {
            return 0;
        }

        $start = $day->setTimezone(UserActivityClock::timezone())->startOfDay();
        $date = $start->toDateString();
        $counts = self::eventCounts($userIds, $start);
        $timestamp = now();
        $rows = [];

        foreach ($userIds as $userId) {
            $summary = UserActivityMinutes::summarize($store->activityMinutes($userId, $dayKey));

            if ($summary['online'] === 0) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'activity_date' => $date,
                'first_minute' => $summary['first'],
                'last_minute' => $summary['last'],
                'online_minutes' => $summary['online'],
                'active_minutes' => $summary['active'],
                'idle_minutes' => $summary['idle'],
                'background_minutes' => $summary['background'],
                'page_views' => $counts[$userId]['page'] ?? 0,
                'actions' => $counts[$userId]['action'] ?? 0,
                'downloads' => $counts[$userId]['download'] ?? 0,
                'hourly_active' => json_encode($summary['hourly']),
                'minute_states' => $summary['states'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            UserActivityDay::query()->upsert(
                $chunk,
                ['user_id', 'activity_date'],
                ['first_minute', 'last_minute', 'online_minutes', 'active_minutes', 'idle_minutes', 'background_minutes', 'page_views', 'actions', 'downloads', 'hourly_active', 'minute_states', 'updated_at'],
            );
        }

        return count($rows);
    }

    /**
     * @return list<CarbonImmutable>
     */
    public static function daysToFlush(CarbonImmutable $now): array
    {
        $local = $now->setTimezone(UserActivityClock::timezone());
        $days = [$local];

        /** Los últimos minutos de ayer se terminan de cerrar en la primera media hora del día. */
        if ($local->hour === 0 && $local->minute < 30) {
            $days[] = $local->subDay();
        }

        return $days;
    }

    /**
     * Páginas, acciones y descargas del día por usuario, en una sola consulta.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    private static function eventCounts(array $userIds, CarbonImmutable $start): array
    {
        $counts = [];

        UserActivityEvent::query()
            ->select('user_id', 'type', DB::raw('COUNT(*) as total'))
            ->whereIn('user_id', $userIds)
            ->whereIn('type', ['page', 'action', 'download'])
            ->whereBetween('occurred_at', [$start->format('Y-m-d H:i:s'), $start->endOfDay()->format('Y-m-d H:i:s')])
            ->groupBy('user_id', 'type')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$counts): void {
                $counts[(int) $row->user_id][(string) $row->type] = (int) $row->total;
            });

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function eventRow(array $item): ?array
    {
        $userId = (int) ($item['user_id'] ?? 0);
        $key = (string) ($item['event_key'] ?? '');
        $occurredAt = (string) ($item['occurred_at'] ?? '');

        if ($userId <= 0 || strlen($key) !== 36 || $occurredAt === '' || trim((string) ($item['label'] ?? '')) === '') {
            return null;
        }

        return [
            'event_key' => $key,
            'user_id' => $userId,
            'occurred_at' => $occurredAt,
            'type' => mb_substr((string) ($item['type'] ?? 'page'), 0, 20),
            'label' => mb_substr((string) $item['label'], 0, 255),
            'panel' => isset($item['panel']) ? mb_substr((string) $item['panel'], 0, 60) : null,
            'page' => isset($item['page']) ? mb_substr((string) $item['page'], 0, 255) : null,
            'path' => isset($item['path']) ? mb_substr((string) $item['path'], 0, 300) : null,
            'duration_ms' => isset($item['duration_ms']) && is_numeric($item['duration_ms']) ? (int) $item['duration_ms'] : null,
            'status' => isset($item['status']) && is_numeric($item['status']) ? (int) $item['status'] : null,
            'created_at' => now(),
        ];
    }
}
