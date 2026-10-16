<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Models\User;
use App\Models\UserWorkAbsence;
use App\Support\UserActivity\UserActivityClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Arma la jornada de cada persona y día. Carga en lote (ausencias y guardias en
 * pocas consultas para todo el grupo), así que el tablero en vivo no hace una
 * consulta por persona.
 *
 * Precedencia: exenta > ausencia > feriado > fin de semana > descanso
 * post-guardia > día laboral (o «sin turno» para quien trabaja por guardias).
 */
final class WorkDayPlanner
{
    private const CACHE_PREFIX = 'ua:workday-plan:';

    /**
     * @param  Collection<int, User>  $users  Por id.
     * @return array<int, array<string, WorkDayPlan>> Usuario => fecha `Y-m-d` => plan.
     */
    public static function plans(Collection $users, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = UserActivityClock::timezone();
        $from = $from->setTimezone($timezone)->startOfDay();
        $to = $to->setTimezone($timezone)->startOfDay();

        if ($users->isEmpty() || $from->greaterThan($to)) {
            return [];
        }

        $users = $users->keyBy('id');
        $absences = self::absences($users->keys()->all(), $from, $to);
        $guards = WorkGuardSource::windows($users, $from->subDay(), $to);
        $plans = [];

        foreach ($users as $userId => $user) {
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $plans[(int) $userId][$day->toDateString()] = self::build($user, $day, $absences[(int) $userId] ?? [], $guards[(int) $userId] ?? []);
            }
        }

        return $plans;
    }

    public static function forDay(User $user, CarbonImmutable $date): WorkDayPlan
    {
        $date = $date->setTimezone(UserActivityClock::timezone())->startOfDay();

        return self::plans(new Collection([$user->id => $user]), $date, $date)[(int) $user->id][$date->toDateString()];
    }

    /**
     * Plan de hoy guardado unos minutos: lo consulta el botón de almuerzo en cada
     * refresco. Cambiar la jornada o una ausencia lo invalida al instante.
     */
    public static function cachedToday(User $user): WorkDayPlan
    {
        $today = UserActivityClock::now()->startOfDay();
        $key = self::cacheKey((int) $user->id, $today->toDateString());

        try {
            $cached = Cache::get($key);

            if ($cached instanceof WorkDayPlan && $cached->date === $today->toDateString()) {
                return $cached;
            }
        } catch (Throwable) {
            $cached = null;
        }

        $plan = self::forDay($user, $today);

        try {
            Cache::put($key, $plan, 300);
        } catch (Throwable) {
            // Sin caché se recalcula en el próximo refresco.
        }

        return $plan;
    }

    public static function forget(int $userId): void
    {
        $today = UserActivityClock::now()->startOfDay();

        try {
            foreach ([$today->subDay(), $today, $today->addDay()] as $day) {
                Cache::forget(self::cacheKey($userId, $day->toDateString()));
            }
        } catch (Throwable) {
            // Vence solo en 5 minutos.
        }
    }

    /**
     * @param  list<UserWorkAbsence>  $absences
     * @param  array<string, list<array{from: int, to: int, label: string}>>  $guards
     */
    private static function build(User $user, CarbonImmutable $day, array $absences, array $guards): WorkDayPlan
    {
        $date = $day->toDateString();
        $yesterday = $day->subDay()->toDateString();
        $schedule = WorkScheduleType::fromStored($user->getAttributes()['work_schedule'] ?? null);

        $ownToday = $guards[$date] ?? [];
        $ownYesterday = $guards[$yesterday] ?? [];
        $overtimeToday = self::isRestDay($day);
        $overtimeYesterday = self::isRestDay($day->subDay());

        $inDay = [];
        $spill = [];

        foreach ($ownYesterday as $window) {
            if ($window['to'] > 1440) {
                $inDay[] = ['from' => 0, 'to' => min(1440, $window['to'] - 1440), 'owner' => $yesterday, 'label' => $window['label'], 'overtime' => $overtimeYesterday];
            }
        }

        foreach ($ownToday as $window) {
            $inDay[] = ['from' => $window['from'], 'to' => min(1440, $window['to']), 'owner' => $date, 'label' => $window['label'], 'overtime' => $overtimeToday];

            if ($window['to'] > 1440) {
                $spill[] = ['from' => 0, 'to' => min(1440, $window['to'] - 1440), 'label' => $window['label'], 'overtime' => $overtimeToday];
            }
        }

        $absence = null;

        foreach ($absences as $candidate) {
            if ($candidate->starts_on->toDateString() <= $date && $candidate->ends_on->toDateString() >= $date) {
                $absence = $candidate;

                break;
            }
        }

        $holiday = WorkHolidays::nameFor($day);
        $weekend = ! in_array($day->dayOfWeekIso, WorkdayConfig::weekdays(), true);
        $restAfterGuard = WorkdayConfig::restAfterOvernightGuard()
            && collect($ownYesterday)->contains(static fn (array $window): bool => $window['to'] > 1440);
        $hasDaytimeShift = collect($ownToday)->contains(static fn (array $window): bool => $window['from'] < WorkdayConfig::workEnd() && $window['to'] > WorkdayConfig::workStart() && $window['to'] <= 1440);

        [$kind, $note] = match (true) {
            $schedule === WorkScheduleType::Exempt => [WorkDayKind::Exempt, null],
            $absence !== null => [WorkDayKind::Absence, $absence->type->label().($absence->reason ? ' · '.$absence->reason : '')],
            $holiday !== null => [WorkDayKind::Holiday, $holiday],
            $weekend => [WorkDayKind::Weekend, null],
            $restAfterGuard => [WorkDayKind::GuardRest, null],
            $schedule === WorkScheduleType::Guards && ! $hasDaytimeShift => [WorkDayKind::NoShift, null],
            default => [WorkDayKind::Workday, null],
        };

        $working = $kind === WorkDayKind::Workday;

        return new WorkDayPlan(
            date: $date,
            kind: $kind,
            schedule: $schedule,
            note: $note,
            workStart: $working ? WorkdayConfig::workStart() : null,
            workEnd: $working ? WorkdayConfig::workEnd() : null,
            guards: $inDay,
            spill: $spill,
        );
    }

    /**
     * Fin de semana o feriado: una guardia ese día cuenta como horas extra.
     */
    private static function isRestDay(CarbonImmutable $day): bool
    {
        return ! in_array($day->dayOfWeekIso, WorkdayConfig::weekdays(), true) || WorkHolidays::nameFor($day) !== null;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<UserWorkAbsence>>
     */
    private static function absences(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byUser = [];

        UserWorkAbsence::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->orderBy('starts_on')
            ->get(['id', 'user_id', 'type', 'starts_on', 'ends_on', 'reason'])
            ->each(static function (UserWorkAbsence $absence) use (&$byUser): void {
                $byUser[(int) $absence->user_id][] = $absence;
            });

        return $byUser;
    }

    private static function cacheKey(int $userId, string $date): string
    {
        return self::CACHE_PREFIX.$userId.':'.$date;
    }
}
