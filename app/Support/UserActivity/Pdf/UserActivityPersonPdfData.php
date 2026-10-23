<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Pdf;

use App\Models\User;
use App\Models\UserActivityEvent;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityProfile;
use App\Support\UserActivity\UserActivityReport;
use App\Support\UserActivity\UserActivityState;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkDayKind;
use App\Support\UserActivity\Workday\WorkDayPlan;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use App\Support\UserActivity\Workday\WorkMinuteKind;
use App\Support\UserActivity\Workday\WorkScheduleType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Arma los datos del PDF de actividad de una persona en un rango: las mismas cifras
 * del monitor (reporte, días, mapa de calor, pantallas y paneles) y una lectura en
 * lenguaje claro del período.
 *
 * Todo sale de los resúmenes diarios y de un conteo de eventos: un rango de un mes
 * cuesta unas pocas consultas, no una por día.
 */
final class UserActivityPersonPdfData
{
    /** Tope del rango, igual que cuando el PDF traía el recorrido de cada día. */
    public const MAX_DAYS = 31;

    /**
     * @return array<string, mixed>
     */
    public static function build(int $userId, CarbonImmutable $from, CarbonImmutable $to, ?User $generatedBy = null): array
    {
        $timezone = UserActivityClock::timezone();
        $from = $from->setTimezone($timezone)->startOfDay();
        $to = $to->setTimezone($timezone)->startOfDay();

        self::assertRange($from, $to);

        $user = User::query()->find($userId, UserActivityProfile::COLUMNS);

        if ($user === null) {
            throw new InvalidArgumentException('La persona ya no existe.');
        }

        $workdayEnabled = WorkdayConfig::enabled();
        $workdayUser = $workdayEnabled ? User::query()->find($userId, WorkdaySubjects::columns()) : null;
        $subject = $workdayUser !== null && WorkdaySubjects::isSubject($workdayUser);
        $schedule = $subject ? WorkScheduleType::fromStored($workdayUser->getAttributes()['work_schedule'] ?? null) : null;
        /** @var array<string, WorkDayPlan> $plans */
        $plans = $subject ? (WorkDayPlanner::plans(collect([$workdayUser->id => $workdayUser]), $from, $to)[$workdayUser->id] ?? []) : [];

        $totals = collect(UserActivityReport::summary($from, $to)['rows'])->firstWhere('user_id', $userId) ?? self::emptyTotals();
        $person = UserActivityReport::person($userId, $from, $to);
        $away = UserActivityEvent::query()
            ->where('user_id', $userId)
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s'), $to->endOfDay()->format('Y-m-d H:i:s')])
            ->where('type', 'visibility')
            ->where('label', 'like', 'Dejó%')
            ->count();
        $recorded = collect($person['days'])->keyBy('date');
        $days = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $day = $recorded->get($key);
            /** Antes de que empezara a medirse la jornada no hay «día laboral» que cumplir. */
            $plan = WorkdayConfig::appliesTo($date) ? ($plans[$key] ?? null) : null;
            $segments = $day['segments'] ?? [];

            $days[] = [
                'date' => $key,
                'label' => ucfirst($date->translatedFormat('l d \d\e F Y')),
                'short' => ucfirst($date->translatedFormat('D d M')),
                'recorded' => $day !== null,
                'day' => $day,
                'plan_kind' => $plan?->kind,
                'plan_label' => $plan?->label(),
                'work_start' => $plan?->workStart,
                'work_end' => $plan?->workEnd,
                'detail_available' => $day === null || $segments !== [] || ($day['online'] ?? 0) === 0,
            ];
        }

        return [
            'user' => $user,
            'profile' => UserActivityProfile::classify($user),
            'from' => $from,
            'to' => $to,
            'range_label' => $from->isSameDay($to) ? $from->translatedFormat('d \d\e F Y') : $from->translatedFormat('d \d\e F').' al '.$to->translatedFormat('d \d\e F Y'),
            'generated_at' => UserActivityClock::now()->translatedFormat('d/m/Y h:i a'),
            'generated_by' => $generatedBy?->name,
            'workday_enabled' => $workdayEnabled,
            'subject' => $subject,
            'schedule' => $schedule,
            'lunch_limit' => WorkdayConfig::lunchMinutes(),
            'totals' => $totals,
            'person' => $person,
            'days' => $days,
            'highlights' => self::highlights($totals, $days, $person, $subject, $away),
        ];
    }

    public static function assertRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        if ($to->lessThan($from)) {
            throw new InvalidArgumentException('La fecha «Hasta» no puede ser anterior a «Desde».');
        }

        if ($to->greaterThan(UserActivityClock::now()->startOfDay())) {
            throw new InvalidArgumentException('El rango no puede terminar después de hoy.');
        }

        if ((int) $from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw new InvalidArgumentException('El rango puede ser de hasta '.self::MAX_DAYS.' días.');
        }
    }

    /**
     * Lectura del período en frases cortas: qué hizo, cuándo y cómo, con cifras exactas.
     *
     * @param  array<string, mixed>  $totals
     * @param  list<array<string, mixed>>  $days
     * @param  array<string, mixed>  $person
     * @param  int  $away  Veces que dejó el sistema en otra pestaña.
     * @return list<array{title: string, text: string, tone: string}>
     */
    private static function highlights(array $totals, array $days, array $person, bool $subject, int $away): array
    {
        $fmt = static fn (int $minutes): string => UserActivityClock::formatDuration($minutes);
        $clock = static fn (?int $minute): string => $minute === null ? '—' : UserActivityClock::formatMinute($minute);
        $items = [];
        $calendarDays = count($days);
        $connected = array_values(array_filter($days, static fn (array $day): bool => $day['recorded'] && (int) ($day['day']['online'] ?? 0) > 0));
        $connectedCount = count($connected);

        $items[] = ['title' => 'Días con conexión', 'tone' => 'info', 'text' => 'Se conectó '.$connectedCount.' de '.$calendarDays.' '.($calendarDays === 1 ? 'día' : 'días').' del período'
            .($connectedCount > 0 ? ', con '.$fmt((int) $totals['online']).' con el sistema abierto y '.$fmt((int) $totals['active']).' usándolo (uso real '.$totals['usage'].' %). En promedio, '.$fmt((int) round($totals['active'] / $connectedCount)).' activo por día.' : '.')];

        if ($connectedCount > 0) {
            $items[] = ['title' => 'Horario habitual', 'tone' => 'info', 'text' => 'Su primera conexión promedio fue a las '.$clock($totals['avg_first']).' y la última a las '.$clock($totals['avg_last']).'.'
                .self::extremes($connected, $clock)];
        }

        if ($subject) {
            $workdays = array_values(array_filter($days, static fn (array $day): bool => $day['plan_kind'] === WorkDayKind::Workday));
            /** Hoy no cuenta como «no se conectó»: la jornada puede no haber empezado. */
            $today = UserActivityClock::now()->toDateString();
            $missing = array_values(array_filter($workdays, static fn (array $day): bool => $day['date'] !== $today && (! $day['recorded'] || (int) ($day['day']['online'] ?? 0) === 0)));
            $usageText = $totals['work_usage'] !== null ? 'Uso de jornada de '.$totals['work_usage'].' %: '.$fmt((int) $totals['work_active'] + (int) $totals['work_meeting']).' activos o en reunión de '.$fmt((int) $totals['work_base']).' de jornada.' : 'Sin jornada que medir en el período.';
            $items[] = ['title' => 'Jornada laboral', 'tone' => ($totals['work_usage'] ?? 100) < 50 ? 'bad' : 'good', 'text' => count($workdays).' '.(count($workdays) === 1 ? 'día laboral' : 'días laborales').' en el período. '.$usageText
                .' Inactivo en jornada: '.$fmt((int) $totals['work_idle']).'.'];

            $absentTone = (int) $totals['work_absent'] > 0 || $missing !== [] ? 'bad' : 'good';
            $items[] = ['title' => 'Ausencias', 'tone' => $absentTone, 'text' => ((int) $totals['work_absent'] > 0 ? 'Estuvo '.$fmt((int) $totals['work_absent']).' en su horario sin el sistema abierto.' : 'No tuvo minutos de ausencia en su horario.')
                .($missing !== [] ? ' No se conectó en '.count($missing).' '.(count($missing) === 1 ? 'día laboral' : 'días laborales').': '.implode(', ', array_map(static fn (array $day): string => $day['short'], $missing)).'.' : '')];

            $lunchDays = array_filter($workdays, static fn (array $day): bool => (bool) ($day['day']['workday']['lunch_declared'] ?? false));
            $excessDays = array_filter($workdays, static fn (array $day): bool => (int) ($day['day']['workday']['lunch_excess'] ?? 0) > 0);
            $items[] = ['title' => 'Almuerzo', 'tone' => $excessDays !== [] ? 'warn' : 'info', 'text' => 'Declaró almuerzo '.count($lunchDays).' de '.count($workdays).' '.(count($workdays) === 1 ? 'día laboral' : 'días laborales').' ('.$fmt((int) $totals['lunch']).' en total).'
                .($excessDays !== [] ? ' Se pasó de la hora en '.count($excessDays).' '.(count($excessDays) === 1 ? 'día' : 'días').', '.$fmt((int) $totals['lunch_excess']).' de exceso.' : ' Sin excesos de almuerzo.')];

            $meetings = array_sum(array_map(static fn (array $day): int => count($day['day']['workday']['meetings'] ?? []), $days));
            $overtimeDays = array_filter($days, static fn (array $day): bool => (int) ($day['day']['workday']['overtime'] ?? 0) > 0);
            $items[] = ['title' => 'Reuniones y Activo Plus', 'tone' => 'info', 'text' => ($meetings > 0 ? 'Declaró '.$meetings.' '.($meetings === 1 ? 'reunión' : 'reuniones').' ('.$fmt((int) $totals['meeting']).').' : 'No declaró reuniones.')
                .((int) $totals['overtime'] > 0 ? ' Sumó '.$fmt((int) $totals['overtime']).' de Activo Plus (horas extra) en '.count($overtimeDays).' '.(count($overtimeDays) === 1 ? 'día' : 'días').'.' : ' Sin Activo Plus (horas extra).')
                .((int) $totals['guard'] > 0 ? ' Guardias: '.$fmt((int) $totals['guard']).', '.($totals['guard_presence'] ?? 0).' % con el sistema abierto.' : '')];
        }

        $longest = self::longestActive($connected);

        if ($longest !== null) {
            $items[] = ['title' => 'Concentración', 'tone' => 'info', 'text' => 'Su tramo más largo usando el sistema sin pausa fue de '.$fmt($longest['minutes']).', el '.$longest['day'].' de '.$clock($longest['from']).' a '.$clock($longest['to'] + 1).'.'
                .self::peak($person)];
        }

        $items[] = ['title' => 'Otras pestañas', 'tone' => (int) $totals['background'] > (int) $totals['active'] ? 'warn' : 'info', 'text' => 'Tuvo el sistema en otra pestaña o minimizado '.$fmt((int) $totals['background']).' y abierto sin tocarlo '.$fmt((int) $totals['idle']).'.'
            .($away > 0 ? ' Salió a otra pestaña '.number_format($away, 0, ',', '.').' '.($away === 1 ? 'vez' : 'veces').($connectedCount > 0 ? ' (unas '.number_format((int) round($away / $connectedCount), 0, ',', '.').' por día).' : '.') : '')];

        $topPage = $person['top_pages'][0] ?? null;
        $topPanel = $person['top_panels'][0] ?? null;
        $downloads = (int) $totals['downloads'];
        $items[] = ['title' => 'Qué hizo en el sistema', 'tone' => 'info', 'text' => number_format((int) $totals['actions'], 0, ',', '.').' acciones y '.number_format((int) $totals['pages'], 0, ',', '.').' pantallas abiertas'.($downloads > 0 ? ', '.number_format($downloads, 0, ',', '.').' '.($downloads === 1 ? 'documento descargado' : 'documentos descargados') : '').'.'
            .($topPanel !== null ? ' Trabajó sobre todo en '.$topPanel['label'].'.' : '')
            .($topPage !== null ? ' La pantalla que más abrió fue «'.$topPage['label'].'» ('.$topPage['total'].' '.($topPage['total'] === 1 ? 'vez' : 'veces').').' : '')];

        $withoutDetail = array_filter($days, static fn (array $day): bool => ! $day['detail_available']);

        if ($withoutDetail !== []) {
            $items[] = ['title' => 'Detalle no disponible', 'tone' => 'warn', 'text' => 'El recorrido minuto a minuto se guarda 90 días: para '.count($withoutDetail).' '.(count($withoutDetail) === 1 ? 'día' : 'días').' del período solo quedan las cifras del resumen.'];
        }

        /** «5:00 p. m.» o «Vie. 23 oct.» al final de una frase no llevan un segundo punto. */
        return array_map(static fn (array $item): array => [...$item, 'text' => preg_replace('/(?<!\.)\.\.(?!\.)/u', '.', $item['text']) ?? $item['text']], $items);
    }

    /**
     * @param  list<array<string, mixed>>  $connected
     */
    private static function extremes(array $connected, callable $clock): string
    {
        $firsts = array_filter($connected, static fn (array $day): bool => isset($day['day']['first']));

        if (count($firsts) < 2) {
            return '';
        }

        $earliest = collect($firsts)->sortBy(static fn (array $day): int => (int) $day['day']['first'])->first();
        $latest = collect($firsts)->sortByDesc(static fn (array $day): int => (int) ($day['day']['last'] ?? 0))->first();
        $best = collect($connected)->sortByDesc(static fn (array $day): int => (int) $day['day']['active'])->first();
        $worst = collect($connected)->sortBy(static fn (array $day): int => (int) $day['day']['active'])->first();

        return ' Entró más temprano el '.$earliest['short'].' ('.$clock($earliest['day']['first']).') y se fue más tarde el '.$latest['short'].' ('.$clock($latest['day']['last']).').'
            .' Su día de más uso fue el '.$best['short'].' ('.UserActivityClock::formatDuration((int) $best['day']['active']).' activo) y el de menos, el '.$worst['short'].' ('.UserActivityClock::formatDuration((int) $worst['day']['active']).').';
    }

    /**
     * @param  list<array<string, mixed>>  $connected
     * @return array{minutes: int, from: int, to: int, day: string}|null
     */
    private static function longestActive(array $connected): ?array
    {
        $active = [WorkMinuteKind::WorkActive, WorkMinuteKind::Overtime, WorkMinuteKind::GuardActive];
        $best = null;

        foreach ($connected as $day) {
            $run = null;

            foreach ($day['day']['segments'] ?? [] as $segment) {
                $isActive = in_array($segment['kind'] ?? null, $active, true) || ($segment['state'] ?? null) === UserActivityState::Active;

                if (! $isActive) {
                    $run = null;

                    continue;
                }

                $run = $run !== null && (int) $segment['from'] === $run['to'] + 1
                    ? [...$run, 'to' => (int) $segment['to']]
                    : ['from' => (int) $segment['from'], 'to' => (int) $segment['to']];
                $minutes = $run['to'] - $run['from'] + 1;

                if ($best === null || $minutes > $best['minutes']) {
                    $best = ['minutes' => $minutes, 'from' => $run['from'], 'to' => $run['to'], 'day' => $day['short']];
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private static function peak(array $person): string
    {
        $bestHour = null;
        $bestMinutes = 0;
        $byHour = array_fill(0, 24, 0);

        foreach ($person['heatmap'] as $hours) {
            foreach ($hours as $hour => $minutes) {
                $byHour[$hour] += (int) $minutes;
            }
        }

        foreach ($byHour as $hour => $minutes) {
            if ($minutes > $bestMinutes) {
                $bestMinutes = $minutes;
                $bestHour = $hour;
            }
        }

        return $bestHour === null ? '' : ' La hora en que más usa el sistema es de '.UserActivityClock::formatMinute($bestHour * 60).' a '.UserActivityClock::formatMinute(($bestHour + 1) * 60).'.';
    }

    /**
     * @return array<string, int|null>
     */
    private static function emptyTotals(): array
    {
        return [
            'days' => 0, 'online' => 0, 'active' => 0, 'idle' => 0, 'background' => 0, 'usage' => 0, 'avg_active' => 0,
            'pages' => 0, 'actions' => 0, 'downloads' => 0, 'avg_first' => null, 'avg_last' => null,
            'workday_days' => 0, 'work_base' => 0, 'work_active' => 0, 'work_idle' => 0, 'work_absent' => 0, 'lunch' => 0,
            'lunch_excess' => 0, 'overtime' => 0, 'guard' => 0, 'guard_present' => 0, 'meeting' => 0, 'work_meeting' => 0,
            'work_usage' => null, 'guard_presence' => null,
        ];
    }
}
