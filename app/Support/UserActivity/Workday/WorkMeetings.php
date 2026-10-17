<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Models\User;
use App\Models\UserWorkBreak;
use App\Models\UserWorkMeeting;
use App\Support\SecurityAudit;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Las reuniones que declara cada colaborador.
 *
 * - Varias por día, con motivo obligatorio; nunca dos abiertas a la vez.
 * - En reunión puede cerrar sesión: ese tiempo no es inactividad. Dentro de la
 *   jornada cuenta como trabajo; fuera de ella, como horas extra.
 * - Entrar en reunión cierra el almuerzo abierto (y salir a almorzar, la reunión).
 * - Si no marca el fin: una reunión de jornada se cierra a la hora de salida (pasado
 *   un margen, por si sigue de verdad); una fuera de jornada, al terminar el día con
 *   `meeting_auto_close_minutes`. En ambos casos queda «no marcó el fin».
 */
final class WorkMeetings
{
    public const REASON_MIN = 3;

    public const REASON_MAX = 150;

    /**
     * Lo que ve el colaborador en el botón.
     *
     * @return array{available: bool, open: bool, started_at: int|null, reason: string|null, minutes: int, long: bool, today_count: int, today_minutes: int}
     */
    public static function status(User $user, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $base = ['available' => false, 'open' => false, 'started_at' => null, 'reason' => null, 'minutes' => 0, 'long' => false, 'today_count' => 0, 'today_minutes' => 0];

        if (! WorkdayConfig::appliesTo($now) || ! WorkdaySubjects::isSubject($user)) {
            return $base;
        }

        $today = UserWorkMeeting::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->orderBy('started_at')
            ->get();
        $open = self::openFor((int) $user->id);
        $minutesOf = static fn (UserWorkMeeting $meeting): int => max(0, (int) floor((($meeting->ended_at ?? $now)->getTimestamp() - $meeting->started_at->getTimestamp()) / 60));
        $openMinutes = $open !== null ? $minutesOf($open) : 0;

        return [
            'available' => true,
            'open' => $open !== null,
            'started_at' => $open?->started_at->getTimestamp(),
            'reason' => $open?->reason,
            'minutes' => $openMinutes,
            'long' => $openMinutes >= WorkdayConfig::meetingWarningMinutes(),
            'today_count' => $today->count(),
            'today_minutes' => (int) $today->sum($minutesOf),
        ];
    }

    /**
     * @throws WorkBreakException
     */
    public static function start(User $user, string $reason, ?string $panel = null, ?CarbonImmutable $now = null): UserWorkMeeting
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (! WorkdayConfig::appliesTo($now) || ! WorkdaySubjects::isSubject($user)) {
            throw new WorkBreakException('Tu usuario no tiene jornada laboral registrada en el sistema.');
        }

        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > self::REASON_MAX) {
            throw new WorkBreakException('Indica el motivo de la reunión (entre '.self::REASON_MIN.' y '.self::REASON_MAX.' caracteres).');
        }

        $result = DB::transaction(static function () use ($user, $reason, $panel, $now): array {
            /** Bloquea al usuario: dos pestañas a la vez no abren dos reuniones. */
            User::query()->whereKey($user->id)->lockForUpdate()->value('id');

            $open = self::openFor((int) $user->id);

            if ($open !== null) {
                return [$open, false];
            }

            return [UserWorkMeeting::query()->create([
                'user_id' => $user->id,
                'work_date' => $now->toDateString(),
                'started_at' => $now->format('Y-m-d H:i:s'),
                'reason' => $reason,
                'started_from' => $panel !== null ? mb_substr($panel, 0, 60) : null,
            ]), true];
        });

        [$meeting, $created] = $result;

        if (! $created) {
            return $meeting;
        }

        /** No se puede estar en reunión y almorzando: el almuerzo abierto termina aquí. */
        WorkBreaks::end($user, UserWorkBreak::END_MEETING, $now);

        SecurityAudit::log('AUDIT_WORK_MEETING_STARTED', 'work-meeting.start', ['work_meeting_id' => $meeting->id, 'reason' => $reason, 'panel' => $panel], $user);
        UserActivityTracker::recordStep((int) $user->id, ['type' => 'meeting', 'label' => 'Entró en reunión: '.$reason, 'panel' => $panel], $now->getTimestamp());

        return $meeting;
    }

    /**
     * Marca el fin de la reunión abierta. Sin reunión abierta no hace nada.
     */
    public static function end(User $user, string $reason = UserWorkMeeting::END_MANUAL, ?CarbonImmutable $now = null): ?UserWorkMeeting
    {
        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $reason = in_array($reason, [UserWorkMeeting::END_MANUAL, UserWorkMeeting::END_PROMPT, UserWorkMeeting::END_LUNCH], true) ? $reason : UserWorkMeeting::END_MANUAL;

        $meeting = DB::transaction(static function () use ($user, $now, $reason): ?UserWorkMeeting {
            $meeting = UserWorkMeeting::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($meeting === null) {
                return null;
            }

            $meeting->forceFill([
                'ended_at' => ($now->lessThan($meeting->started_at) ? $meeting->started_at : $now)->format('Y-m-d H:i:s'),
                'end_reason' => $reason,
            ])->save();

            return $meeting;
        });

        if ($meeting === null) {
            return null;
        }

        $minutes = max(0, (int) floor(($meeting->ended_at->getTimestamp() - $meeting->started_at->getTimestamp()) / 60));

        SecurityAudit::log('AUDIT_WORK_MEETING_ENDED', 'work-meeting.end', ['work_meeting_id' => $meeting->id, 'minutes' => $minutes, 'reason' => $reason], $user);
        UserActivityTracker::recordStep((int) $user->id, [
            'type' => 'meeting',
            'label' => 'Terminó la reunión «'.$meeting->reason.'» · '.UserActivityClock::formatDuration($minutes),
        ], $now->getTimestamp());

        return $meeting;
    }

    /**
     * Cierra las reuniones sin fin marcado. Lo llama el volcado de cada minuto;
     * repetirlo no cambia nada.
     */
    public static function closeExpired(?CarbonImmutable $now = null): int
    {
        if (! WorkdayConfig::enabled()) {
            return 0;
        }

        $now = ($now ?? UserActivityClock::now())->setTimezone(UserActivityClock::timezone());
        $open = UserWorkMeeting::query()->whereNull('ended_at')->orderBy('id')->limit(500)->get();

        if ($open->isEmpty()) {
            return 0;
        }

        $users = WorkdaySubjects::all()->only($open->pluck('user_id')->unique()->all())->keyBy('id');
        $closed = 0;

        foreach ($open as $meeting) {
            $user = $users->get($meeting->user_id);
            $plan = $user !== null ? WorkDayPlanner::forDay($user, CarbonImmutable::parse($meeting->work_date->toDateString(), UserActivityClock::timezone())) : null;
            $closing = self::autoClosing($meeting, $plan, $now);

            if ($closing === null) {
                continue;
            }

            $closed += UserWorkMeeting::query()
                ->whereKey($meeting->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $closing->format('Y-m-d H:i:s'), 'end_reason' => UserWorkMeeting::END_AUTO, 'updated_at' => now()]);
        }

        return $closed;
    }

    /**
     * Cuándo se cierra sola una reunión, o null si aún no toca.
     */
    public static function autoClosing(UserWorkMeeting $meeting, ?WorkDayPlan $plan, CarbonImmutable $now): ?CarbonImmutable
    {
        $day = CarbonImmutable::parse($meeting->work_date->toDateString(), UserActivityClock::timezone())->startOfDay();
        $start = $meeting->started_at->setTimezone(UserActivityClock::timezone());
        $startMinute = $start->isSameDay($day) ? UserActivityClock::minuteOfDay($start) : 0;

        if ($plan !== null && $plan->hasJornada() && $startMinute < (int) $plan->workEnd) {
            $workEnd = $day->addMinutes((int) $plan->workEnd);

            return $now->greaterThanOrEqualTo($workEnd->addMinutes(WorkdayConfig::meetingCloseGraceMinutes())) ? $workEnd : null;
        }

        $endOfDay = $day->addDay();

        if ($now->lessThan($endOfDay)) {
            return null;
        }

        $closing = $start->addMinutes(WorkdayConfig::meetingAutoCloseMinutes());

        return $closing->greaterThan($endOfDay) ? $endOfDay : $closing;
    }

    /**
     * Reuniones de varios usuarios en un rango, en minutos del día.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, list<array{start: int, end: int|null, reason: string, end_reason: string|null}>>>
     */
    public static function forUsers(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($userIds === [] || ! WorkdayConfig::enabled()) {
            return [];
        }

        $byUser = [];

        UserWorkMeeting::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('started_at')
            ->get(['user_id', 'work_date', 'started_at', 'ended_at', 'end_reason', 'reason'])
            ->each(static function (UserWorkMeeting $meeting) use (&$byUser): void {
                $byUser[(int) $meeting->user_id][$meeting->work_date->toDateString()][] = self::asMinutes($meeting);
            });

        return $byUser;
    }

    /**
     * @return array{start: int, end: int|null, reason: string, end_reason: string|null}
     */
    public static function asMinutes(UserWorkMeeting $meeting): array
    {
        $start = $meeting->started_at->setTimezone(UserActivityClock::timezone());
        $end = $meeting->ended_at?->setTimezone(UserActivityClock::timezone());

        return [
            'start' => UserActivityClock::minuteOfDay($start),
            /** Una reunión que pasa de la medianoche se corta en el día en que empezó. */
            'end' => $end === null ? null : ($end->isSameDay($start) ? UserActivityClock::minuteOfDay($end) : 1440),
            'reason' => (string) $meeting->reason,
            'end_reason' => $meeting->end_reason,
        ];
    }

    public static function openFor(int $userId): ?UserWorkMeeting
    {
        return UserWorkMeeting::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();
    }
}
