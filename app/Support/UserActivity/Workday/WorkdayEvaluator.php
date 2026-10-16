<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Support\UserActivity\UserActivityClock;
use Carbon\CarbonImmutable;

/**
 * Evalúa un día ya armado (plan + minutos + almuerzo) sabiendo qué parte del
 * día ya pasó: hoy se mide hasta el minuto actual, un día cerrado completo.
 */
final class WorkdayEvaluator
{
    /**
     * @param  array<int, string>  $minutes
     * @param  array{start: int, end: int|null, reason?: string|null}|null  $lunch
     * @param  array<int, string>  $nextMinutes
     * @return array{summary: array<string, mixed>, codes: string, segments: list<array{from: int, to: int, kind: WorkMinuteKind, note: string}>, bands: list<array{from: int, to: int, type: string, label: string}>}
     */
    public static function evaluate(WorkDayPlan $plan, array $minutes, ?array $lunch, ?CarbonImmutable $now = null, array $nextMinutes = []): array
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $date = CarbonImmutable::parse($plan->date, UserActivityClock::timezone())->startOfDay();

        return WorkDayClassifier::classify(
            $plan,
            $minutes,
            $lunch,
            self::cutoff($date, $now),
            $nextMinutes,
            self::cutoff($date->addDay(), $now),
        );
    }

    /**
     * Primer minuto del día que aún no pasó.
     */
    public static function cutoff(CarbonImmutable $date, CarbonImmutable $now): int
    {
        $date = $date->setTimezone(UserActivityClock::timezone())->startOfDay();
        $today = $now->setTimezone(UserActivityClock::timezone())->startOfDay();

        return match (true) {
            $date->equalTo($today) => UserActivityClock::minuteOfDay($now),
            $date->lessThan($today) => 1440,
            default => 0,
        };
    }

    /**
     * Columnas de `user_activity_days` con las cifras de la jornada.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, int|string>
     */
    public static function dayColumns(array $summary): array
    {
        $clamp = static fn (mixed $value): int => max(0, min(65535, (int) $value));

        return [
            'workday_kind' => $summary['kind'] instanceof WorkDayKind ? $summary['kind']->value : WorkDayKind::Workday->value,
            'work_base_minutes' => $clamp($summary['base']),
            'work_active_minutes' => $clamp($summary['work_active']),
            'work_idle_minutes' => $clamp($summary['work_idle']),
            'work_absent_minutes' => $clamp($summary['work_absent']),
            'lunch_minutes' => $clamp($summary['lunch']),
            'lunch_excess_minutes' => $clamp($summary['lunch_excess']),
            'overtime_minutes' => $clamp($summary['overtime']),
            'guard_minutes' => $clamp($summary['guard']),
            'guard_present_minutes' => $clamp($summary['guard_present']),
            'guard_active_minutes' => $clamp($summary['guard_active']),
        ];
    }

    /**
     * Uso de jornada de cifras sumadas (rango o grupo).
     */
    public static function usage(int $workActive, int $base): ?int
    {
        return $base > 0 ? min(100, (int) round($workActive * 100 / $base)) : null;
    }
}
