<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Models\User;
use App\Models\UserWorkBreak;
use App\Support\SecurityAudit;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El almuerzo que declara cada colaborador.
 *
 * - Uno por día, iniciado entre `lunch_earliest` y `lunch_latest` de un día laboral.
 * - Iniciar y volver son idempotentes: un doble clic o dos pestañas no crean dos
 *   almuerzos ni mueven la hora de regreso.
 * - Si no marca el regreso, se cierra solo al terminar la jornada (`auto`) y el
 *   tiempo de más queda como exceso.
 */
final class WorkBreaks
{
    /**
     * Lo que ve el colaborador en el botón.
     *
     * @return array{eligible: bool, state: string, message: string, started_at: int|null, ended_at: int|null, minutes: int, excess: int, limit: int, opens_at: string, closes_at: string}
     */
    public static function status(User $user, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $limit = WorkdayConfig::lunchMinutes();
        $base = [
            'eligible' => false, 'state' => 'hidden', 'message' => '', 'started_at' => null, 'ended_at' => null,
            'minutes' => 0, 'excess' => 0, 'limit' => $limit,
            'opens_at' => UserActivityClock::formatMinute(WorkdayConfig::lunchEarliest()),
            'closes_at' => UserActivityClock::formatMinute(WorkdayConfig::lunchLatest()),
        ];

        if (! WorkdayConfig::appliesTo($now) || ! WorkdaySubjects::isSubject($user)) {
            return $base;
        }

        $plan = WorkDayPlanner::cachedToday($user);

        if (! $plan->lunchAllowed()) {
            return $base;
        }

        $break = self::today((int) $user->id, $now);
        $minuteNow = UserActivityClock::minuteOfDay($now);

        if ($break !== null) {
            $end = $break->ended_at ?? $now;
            $minutes = max(0, (int) floor(($end->getTimestamp() - $break->started_at->getTimestamp()) / 60));

            return [
                ...$base,
                'eligible' => true,
                'state' => $break->ended_at === null ? 'on' : 'done',
                'started_at' => $break->started_at->getTimestamp(),
                'ended_at' => $break->ended_at?->getTimestamp(),
                'minutes' => $minutes,
                'excess' => max(0, $minutes - $limit),
                'message' => $break->ended_at === null
                    ? 'En almuerzo desde las '.UserActivityClock::formatMinute(UserActivityClock::minuteOfDay($break->started_at))
                    : 'Almorzó '.UserActivityClock::formatDuration($minutes).($minutes > $limit ? ' ('.($minutes - $limit).' min de más)' : ''),
            ];
        }

        return match (true) {
            $minuteNow < WorkdayConfig::lunchEarliest() => [...$base, 'eligible' => true, 'state' => 'early', 'message' => 'Podrás salir a almorzar desde las '.$base['opens_at'].'.'],
            $minuteNow >= WorkdayConfig::lunchLatest() => [...$base, 'eligible' => true, 'state' => 'missed', 'message' => 'No declaraste almuerzo hoy: esa hora cuenta como jornada.'],
            default => [...$base, 'eligible' => true, 'state' => 'ready', 'message' => 'Tienes '.$limit.' minutos. Al volver, pulsa «Volver del almuerzo».'],
        };
    }

    /**
     * @throws WorkBreakException
     */
    public static function start(User $user, ?string $panel = null, ?CarbonImmutable $now = null): UserWorkBreak
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());

        if (! WorkdayConfig::appliesTo($now) || ! WorkdaySubjects::isSubject($user)) {
            throw new WorkBreakException('Tu usuario no tiene jornada laboral registrada en el sistema.');
        }

        $plan = WorkDayPlanner::forDay($user, $now);

        if (! $plan->lunchAllowed()) {
            throw new WorkBreakException('Hoy no tienes jornada laboral ('.mb_strtolower($plan->label()).'): no hace falta declarar almuerzo.');
        }

        $minute = UserActivityClock::minuteOfDay($now);

        if ($minute < WorkdayConfig::lunchEarliest() || $minute >= WorkdayConfig::lunchLatest()) {
            throw new WorkBreakException('El almuerzo se inicia entre las '.UserActivityClock::formatMinute(WorkdayConfig::lunchEarliest()).' y las '.UserActivityClock::formatMinute(WorkdayConfig::lunchLatest()).'.');
        }

        $existing = self::today((int) $user->id, $now);

        if ($existing !== null) {
            if ($existing->ended_at === null) {
                return $existing;
            }

            throw new WorkBreakException('Ya tomaste tu almuerzo hoy ('.UserActivityClock::formatMinute(UserActivityClock::minuteOfDay($existing->started_at)).' – '.UserActivityClock::formatMinute(UserActivityClock::minuteOfDay($existing->ended_at)).').');
        }

        try {
            $break = DB::transaction(static fn (): UserWorkBreak => UserWorkBreak::query()->create([
                'user_id' => $user->id,
                'work_date' => $now->toDateString(),
                'started_at' => $now->format('Y-m-d H:i:s'),
                'started_from' => $panel !== null ? mb_substr($panel, 0, 60) : null,
            ]));
        } catch (QueryException $exception) {
            /** Dos pestañas a la vez: la otra ya lo creó (índice único usuario + día). */
            $break = self::today((int) $user->id, $now) ?? throw $exception;

            return $break;
        }

        SecurityAudit::log('AUDIT_WORK_BREAK_STARTED', 'work-break.start', ['work_break_id' => $break->id, 'panel' => $panel], $user);
        UserActivityTracker::recordStep((int) $user->id, ['type' => 'lunch', 'label' => 'Salió a almorzar', 'panel' => $panel], $now->getTimestamp());

        return $break;
    }

    /**
     * Marca el regreso. Sin almuerzo abierto no hace nada.
     */
    public static function end(User $user, string $reason = UserWorkBreak::END_MANUAL, ?CarbonImmutable $now = null): ?UserWorkBreak
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $reason = in_array($reason, [UserWorkBreak::END_MANUAL, UserWorkBreak::END_PROMPT], true) ? $reason : UserWorkBreak::END_MANUAL;

        $break = DB::transaction(static function () use ($user, $now, $reason): ?UserWorkBreak {
            $break = UserWorkBreak::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($break === null) {
                return null;
            }

            $break->forceFill([
                'ended_at' => self::closingMoment($break, $now)->format('Y-m-d H:i:s'),
                'end_reason' => $reason,
            ])->save();

            return $break;
        });

        if ($break === null) {
            return null;
        }

        $minutes = max(0, (int) floor(($break->ended_at->getTimestamp() - $break->started_at->getTimestamp()) / 60));
        $excess = max(0, $minutes - WorkdayConfig::lunchMinutes());

        SecurityAudit::log('AUDIT_WORK_BREAK_ENDED', 'work-break.end', ['work_break_id' => $break->id, 'minutes' => $minutes, 'excess' => $excess, 'reason' => $reason], $user);
        UserActivityTracker::recordStep((int) $user->id, [
            'type' => 'lunch',
            'label' => 'Volvió del almuerzo · '.UserActivityClock::formatDuration($minutes).($excess > 0 ? ' ('.$excess.' min de más)' : ''),
        ], $now->getTimestamp());

        return $break;
    }

    /**
     * Cierra los almuerzos sin regreso marcado cuando su jornada ya terminó. Lo
     * llama el volcado de cada minuto; repetirlo no cambia nada.
     */
    public static function closeExpired(?CarbonImmutable $now = null): int
    {
        if (! WorkdayConfig::enabled()) {
            return 0;
        }

        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $closed = 0;

        UserWorkBreak::query()
            ->whereNull('ended_at')
            ->whereDate('work_date', '<=', $now->toDateString())
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(static function (UserWorkBreak $break) use ($now, &$closed): void {
                $endOfDay = self::workEndMoment($break);

                if ($now->lessThan($endOfDay)) {
                    return;
                }

                $closed += UserWorkBreak::query()
                    ->whereKey($break->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $endOfDay->format('Y-m-d H:i:s'), 'end_reason' => UserWorkBreak::END_AUTO, 'updated_at' => now()]);
            });

        return $closed;
    }

    /**
     * Almuerzos de varios usuarios en un rango, en minutos del día.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, array{start: int, end: int|null, reason: string|null}>>
     */
    public static function forUsers(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($userIds === [] || ! WorkdayConfig::enabled()) {
            return [];
        }

        $byUser = [];

        UserWorkBreak::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->get(['user_id', 'work_date', 'started_at', 'ended_at', 'end_reason'])
            ->each(static function (UserWorkBreak $break) use (&$byUser): void {
                $byUser[(int) $break->user_id][$break->work_date->toDateString()] = self::asMinutes($break);
            });

        return $byUser;
    }

    /**
     * @return array{start: int, end: int|null, reason: string|null}
     */
    public static function asMinutes(UserWorkBreak $break): array
    {
        $start = $break->started_at->setTimezone(UserActivityClock::timezone());
        $end = $break->ended_at?->setTimezone(UserActivityClock::timezone());

        return [
            'start' => UserActivityClock::minuteOfDay($start),
            /** Un regreso marcado otro día (no debería pasar) se corta a la medianoche. */
            'end' => $end === null ? null : ($end->isSameDay($start) ? UserActivityClock::minuteOfDay($end) : 1440),
            'reason' => $break->end_reason,
        ];
    }

    public static function today(int $userId, ?CarbonImmutable $now = null): ?UserWorkBreak
    {
        $now ??= UserActivityClock::now();

        return UserWorkBreak::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $now->toDateString())
            ->first();
    }

    /**
     * Volver después del fin de la jornada no alarga el almuerzo más allá de ella.
     */
    private static function closingMoment(UserWorkBreak $break, CarbonImmutable $now): CarbonImmutable
    {
        $endOfDay = self::workEndMoment($break);
        $moment = $now->lessThan($endOfDay) ? $now : $endOfDay;

        return $moment->lessThan($break->started_at) ? $break->started_at : $moment;
    }

    private static function workEndMoment(UserWorkBreak $break): CarbonImmutable
    {
        return CarbonImmutable::parse($break->work_date->toDateString(), UserActivityClock::timezone())
            ->startOfDay()
            ->addMinutes(WorkdayConfig::workEnd());
    }
}
