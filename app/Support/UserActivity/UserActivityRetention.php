<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\UserActivityDay;
use App\Models\UserActivityEvent;
use Carbon\CarbonImmutable;

/**
 * Retención de Actividad de usuarios:
 *
 * - recorrido detallado (eventos y barra minuto a minuto): 90 días;
 * - resumen diario: 12 meses.
 *
 * Borra por lotes de ids para no bloquear las tablas y funcionar en cualquier motor.
 */
final class UserActivityRetention
{
    private const CHUNK = 5000;

    /**
     * @return array{events: int, bars: int, days: int}
     */
    public static function purge(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? UserActivityClock::now())->startOfDay();
        $detailCutoff = $today->subDays(max(7, (int) config('live-presence.activity.detail_retention_days', 90)));
        $summaryCutoff = $today->subMonthsNoOverflow(max(1, (int) config('live-presence.activity.summary_retention_months', 12)));

        $events = 0;

        do {
            $ids = UserActivityEvent::query()
                ->where('occurred_at', '<', $detailCutoff->format('Y-m-d H:i:s'))
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id');

            $events += $ids->isEmpty() ? 0 : UserActivityEvent::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === self::CHUNK);

        $bars = UserActivityDay::query()
            ->where('activity_date', '<', $detailCutoff->toDateString())
            ->whereNotNull('minute_states')
            ->update(['minute_states' => null]);

        $days = 0;

        do {
            $ids = UserActivityDay::query()
                ->where('activity_date', '<', $summaryCutoff->toDateString())
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id');

            $days += $ids->isEmpty() ? 0 : UserActivityDay::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === self::CHUNK);

        return ['events' => $events, 'bars' => $bars, 'days' => $days];
    }
}
