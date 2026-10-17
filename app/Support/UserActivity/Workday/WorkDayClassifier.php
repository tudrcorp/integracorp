<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityState;
use Carbon\CarbonImmutable;

/**
 * Cruza los minutos marcados de un día con la jornada que la persona debía cumplir.
 *
 * Regla de cada minuto, en este orden:
 * 1. Dentro del almuerzo declarado: «Almuerzo» los primeros 60 min y «Exceso» después.
 *    Dentro de una reunión declarada: «En reunión» (trabajo en la jornada, horas
 *    extra fuera de ella), aunque no tenga el sistema abierto.
 * 2. Dentro de la franja laboral: activo, inactivo (abierto sin tocarlo u otra
 *    pestaña) o ausente (sin el sistema abierto). Lo que aún no pasó no cuenta.
 * 3. Dentro de una guardia: activo, conectado o sin conexión. Si la guardia es en
 *    fin de semana o feriado, su uso cuenta como horas extra.
 * 4. Fuera de todo: usarlo es «horas extra»; tenerlo abierto sin usarlo, no.
 *
 * Uso de jornada = (activo + en reunión, en jornada) ÷ (franja transcurrida − almuerzo declarado
 * hasta 60 min). Sin almuerzo declarado esa hora sigue siendo jornada: si no se
 * trabajó, cuenta como ausencia.
 */
final class WorkDayClassifier
{
    /**
     * @param  array<int, string>  $minutes  Minuto del día => código de estado.
     * @param  array{start: int, end: int|null, reason?: string|null}|null  $lunch  Minutos del día.
     * @param  int  $cutoff  Primer minuto que aún no pasó (1440 para un día cerrado).
     * @param  array<int, string>  $nextMinutes  Minutos de mañana, para la parte de una guardia que cruza la medianoche.
     * @param  list<array{start: int, end: int|null, reason: string, end_reason?: string|null}>  $meetings  Minutos del día.
     * @return array{summary: array<string, mixed>, codes: string, segments: list<array{from: int, to: int, kind: WorkMinuteKind, note: string}>, bands: list<array{from: int, to: int, type: string, label: string}>}
     */
    public static function classify(WorkDayPlan $plan, array $minutes, ?array $lunch, int $cutoff = 1440, array $nextMinutes = [], int $nextCutoff = 1440, array $meetings = []): array
    {
        $cutoff = max(0, min(1440, $cutoff));
        $lunchMinutes = WorkdayConfig::lunchMinutes();
        $lunchAt = self::lunchMap($plan, $lunch, $cutoff, $lunchMinutes);
        $meetingAt = self::meetingMap($plan, $meetings, $cutoff);
        $guardAt = self::guardMap($plan->guards);
        $exempt = $plan->kind === WorkDayKind::Exempt;

        $codes = str_repeat(WorkMinuteKind::Free->value, 1440);
        $notes = [];
        $totals = [
            'window' => 0, 'work_active' => 0, 'meeting' => 0, 'work_meeting' => 0, 'work_idle' => 0, 'work_absent' => 0, 'lunch' => 0, 'lunch_excess' => 0,
            'overtime' => 0, 'guard' => 0, 'guard_present' => 0, 'guard_active' => 0,
        ];

        for ($minute = 0; $minute < 1440; $minute++) {
            $state = UserActivityState::fromCode($minutes[$minute] ?? null);
            $past = $minute < $cutoff;
            $guard = $guardAt[$minute] ?? null;
            $owned = $guard !== null && $guard['owner'] === $plan->date;

            if ($plan->inWork($minute) && $past) {
                $totals['window']++;
            }

            if (isset($lunchAt[$minute])) {
                $kind = $lunchAt[$minute];
            } elseif (isset($meetingAt[$minute])) {
                $kind = WorkMinuteKind::Meeting;
                $notes[$minute] = $meetingAt[$minute].(! $exempt && ! $plan->inWork($minute) && ($guard === null || $guard['overtime']) ? ' · cuenta como horas extra' : '');
                self::countMeeting($totals, $plan, $minute, $guard, $owned, $exempt);

                $codes[$minute] = $kind->value;

                continue;
            } elseif ($exempt) {
                $kind = self::exemptKind($state);
            } elseif ($plan->inWork($minute)) {
                $kind = $past ? self::workKind($state) : WorkMinuteKind::Free;
            } elseif ($guard !== null && ! $guard['overtime']) {
                $kind = $past ? self::guardKind($state) : WorkMinuteKind::Free;
            } else {
                $kind = self::offHoursKind($state);
            }

            $codes[$minute] = $kind->value;

            if ($guard !== null && $kind->isGuard()) {
                $notes[$minute] = $guard['owner'] === $plan->date ? $guard['label'] : $guard['label'].' del '.self::shortDate($guard['owner']);
            }

            /** La cola de una guardia de ayer se pinta aquí pero cuenta para ayer. */
            if ($guard !== null && ! $owned && ($kind->isGuard() || $kind === WorkMinuteKind::Overtime)) {
                continue;
            }

            self::count($totals, $kind);
        }

        foreach ($plan->spill as $window) {
            for ($minute = $window['from']; $minute < $window['to'] && $minute < $nextCutoff; $minute++) {
                $state = UserActivityState::fromCode($nextMinutes[$minute] ?? null);
                self::count($totals, $window['overtime'] ? self::offHoursKind($state) : self::guardKind($state));
            }
        }

        $base = max(0, $totals['window'] - $totals['lunch']);
        $worked = $totals['work_active'] + $totals['work_meeting'];
        $lunchOpen = $lunch !== null && ($lunch['end'] ?? null) === null && $plan->lunchAllowed();

        return [
            'summary' => [
                ...$totals,
                'regime' => true,
                'kind' => $plan->kind,
                'kind_label' => $plan->label(),
                'schedule' => $plan->schedule,
                'work_start' => $plan->workStart,
                'work_end' => $plan->workEnd,
                'base' => $base,
                'expected' => $plan->hasJornada() ? $plan->windowMinutes() - $lunchMinutes : 0,
                'usage' => $base > 0 ? min(100, (int) round($worked * 100 / $base)) : null,
                'guard_presence' => $totals['guard'] > 0 ? (int) round($totals['guard_present'] * 100 / $totals['guard']) : null,
                'lunch_open' => $lunchOpen,
                'lunch_start' => $lunch['start'] ?? null,
                'lunch_end' => $lunch['end'] ?? null,
                'lunch_reason' => $lunch['reason'] ?? null,
                'lunch_declared' => $lunch !== null,
                'has_guard' => $plan->ownsGuards(),
                'meetings' => $meetings,
                'meeting_open' => collect($meetings)->contains(static fn (array $meeting): bool => ($meeting['end'] ?? null) === null),
            ],
            'codes' => $codes,
            'segments' => self::segments($codes, $notes, $lunch),
            'bands' => self::bands($plan),
        ];
    }

    /**
     * @param  array{start: int, end: int|null, reason?: string|null}|null  $lunch
     * @return array<int, WorkMinuteKind>
     */
    private static function lunchMap(WorkDayPlan $plan, ?array $lunch, int $cutoff, int $lunchMinutes): array
    {
        if ($lunch === null || ! $plan->lunchAllowed()) {
            return [];
        }

        $start = max(0, (int) $lunch['start']);
        $end = $lunch['end'] ?? $cutoff;
        $end = min((int) $end, (int) $plan->workEnd, $cutoff);
        $map = [];

        for ($minute = $start; $minute < $end; $minute++) {
            $map[$minute] = $minute - $start < $lunchMinutes ? WorkMinuteKind::Lunch : WorkMinuteKind::LunchExcess;
        }

        return $map;
    }

    /**
     * Minuto => motivo de la reunión. Una reunión abierta que empezó en la
     * jornada no suma más allá de la hora de salida mientras siga abierta: si se
     * olvidó cerrarla, la tarde no se vuelve horas extra.
     *
     * @param  list<array{start: int, end: int|null, reason: string, end_reason?: string|null}>  $meetings
     * @return array<int, string>
     */
    private static function meetingMap(WorkDayPlan $plan, array $meetings, int $cutoff): array
    {
        $map = [];

        foreach ($meetings as $meeting) {
            $start = max(0, (int) $meeting['start']);
            $end = $meeting['end'] ?? null;

            if ($end === null) {
                $end = $plan->hasJornada() && $start < (int) $plan->workEnd ? min($cutoff, (int) $plan->workEnd) : $cutoff;
            }

            $end = min((int) $end, $cutoff, 1440);
            $note = 'Reunión: '.$meeting['reason'].(($meeting['end_reason'] ?? null) === 'auto' ? ' · no marcó el fin' : '');

            for ($minute = $start; $minute < $end; $minute++) {
                $map[$minute] ??= $note;
            }
        }

        return $map;
    }

    /**
     * Una reunión en la jornada es trabajo; en una guardia, presencia; fuera de todo, horas extra.
     *
     * @param  array<string, int>  $totals
     * @param  array{owner: string, label: string, overtime: bool}|null  $guard
     */
    private static function countMeeting(array &$totals, WorkDayPlan $plan, int $minute, ?array $guard, bool $owned, bool $exempt): void
    {
        $totals['meeting']++;

        if ($exempt) {
            return;
        }

        if ($plan->inWork($minute)) {
            $totals['work_meeting']++;

            return;
        }

        if ($guard !== null && ! $guard['overtime']) {
            if ($owned) {
                $totals['guard']++;
                $totals['guard_present']++;
                $totals['guard_active']++;
            }

            return;
        }

        $totals['overtime']++;
    }

    /**
     * @param  list<array{from: int, to: int, owner: string, label: string, overtime: bool}>  $guards
     * @return array<int, array{owner: string, label: string, overtime: bool}>
     */
    private static function guardMap(array $guards): array
    {
        $map = [];

        foreach ($guards as $guard) {
            for ($minute = max(0, $guard['from']); $minute < min(1440, $guard['to']); $minute++) {
                $map[$minute] ??= ['owner' => $guard['owner'], 'label' => $guard['label'], 'overtime' => $guard['overtime']];
            }
        }

        return $map;
    }

    private static function workKind(UserActivityState $state): WorkMinuteKind
    {
        return match ($state) {
            UserActivityState::Active => WorkMinuteKind::WorkActive,
            UserActivityState::Idle, UserActivityState::Background => WorkMinuteKind::WorkIdle,
            UserActivityState::Offline => WorkMinuteKind::WorkAbsent,
        };
    }

    private static function guardKind(UserActivityState $state): WorkMinuteKind
    {
        return match ($state) {
            UserActivityState::Active => WorkMinuteKind::GuardActive,
            UserActivityState::Idle, UserActivityState::Background => WorkMinuteKind::GuardIdle,
            UserActivityState::Offline => WorkMinuteKind::GuardAbsent,
        };
    }

    private static function offHoursKind(UserActivityState $state): WorkMinuteKind
    {
        return match ($state) {
            UserActivityState::Active => WorkMinuteKind::Overtime,
            UserActivityState::Idle, UserActivityState::Background => WorkMinuteKind::OffHoursIdle,
            UserActivityState::Offline => WorkMinuteKind::Free,
        };
    }

    private static function exemptKind(UserActivityState $state): WorkMinuteKind
    {
        return match ($state) {
            UserActivityState::Active => WorkMinuteKind::WorkActive,
            UserActivityState::Idle, UserActivityState::Background => WorkMinuteKind::WorkIdle,
            UserActivityState::Offline => WorkMinuteKind::Free,
        };
    }

    /**
     * @param  array<string, int>  $totals
     */
    private static function count(array &$totals, WorkMinuteKind $kind): void
    {
        match ($kind) {
            WorkMinuteKind::WorkActive => $totals['work_active']++,
            WorkMinuteKind::WorkIdle => $totals['work_idle']++,
            WorkMinuteKind::WorkAbsent => $totals['work_absent']++,
            WorkMinuteKind::Lunch => $totals['lunch']++,
            WorkMinuteKind::LunchExcess => $totals['lunch_excess']++,
            WorkMinuteKind::Overtime => $totals['overtime']++,
            default => null,
        };

        if ($kind->isGuard()) {
            $totals['guard']++;

            if ($kind !== WorkMinuteKind::GuardAbsent) {
                $totals['guard_present']++;
            }

            if ($kind === WorkMinuteKind::GuardActive) {
                $totals['guard_active']++;
            }
        }
    }

    /**
     * @param  array<int, string>  $notes
     * @param  array{start: int, end: int|null, reason?: string|null}|null  $lunch
     * @return list<array{from: int, to: int, kind: WorkMinuteKind, note: string}>
     */
    private static function segments(string $codes, array $notes, ?array $lunch): array
    {
        $segments = [];
        $current = null;

        for ($minute = 0; $minute < 1440; $minute++) {
            $code = $codes[$minute];
            $note = $notes[$minute] ?? '';

            if ($current !== null && $current['code'] === $code && $current['note'] === $note) {
                $current['to'] = $minute;

                continue;
            }

            if ($current !== null) {
                $segments[] = $current;
            }

            $current = ['from' => $minute, 'to' => $minute, 'code' => $code, 'note' => $note];
        }

        $segments[] = $current;
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === null || $segment['code'] === WorkMinuteKind::Free->value) {
                continue;
            }

            $kind = WorkMinuteKind::from($segment['code']);
            $note = $segment['note'];

            if ($kind === WorkMinuteKind::Lunch && $lunch !== null) {
                $note = 'Salió a las '.UserActivityClock::formatMinute((int) $lunch['start']);
            } elseif ($kind === WorkMinuteKind::LunchExcess) {
                $note = ($segment['to'] - $segment['from'] + 1).' min por encima de la hora de almuerzo'.(($lunch['reason'] ?? null) === 'auto' ? ' · no marcó el regreso' : '');
            }

            $result[] = ['from' => $segment['from'], 'to' => $segment['to'], 'kind' => $kind, 'note' => $note];
        }

        return $result;
    }

    /**
     * Fondo de la barra: la franja laboral y las guardias, para ver también lo que falta del día.
     *
     * @return list<array{from: int, to: int, type: string, label: string}>
     */
    private static function bands(WorkDayPlan $plan): array
    {
        $bands = [];

        if ($plan->hasJornada()) {
            $bands[] = ['from' => (int) $plan->workStart, 'to' => (int) $plan->workEnd, 'type' => 'work', 'label' => 'Jornada '.UserActivityClock::formatMinute($plan->workStart).' – '.UserActivityClock::formatMinute($plan->workEnd)];
        }

        foreach ($plan->guards as $guard) {
            $bands[] = ['from' => $guard['from'], 'to' => $guard['to'], 'type' => $guard['overtime'] ? 'guard-extra' : 'guard', 'label' => $guard['label'].($guard['overtime'] ? ' (cuenta como horas extra)' : '')];
        }

        return $bands;
    }

    private static function shortDate(string $date): string
    {
        return CarbonImmutable::parse($date)->locale('es')->translatedFormat('D d/m');
    }
}
