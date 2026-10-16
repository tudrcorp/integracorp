<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\User;
use App\Support\LivePresence\LivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\UserActivity\Workday\WorkBreaks;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkdayEvaluator;
use App\Support\UserActivity\Workday\WorkDayPlan;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lo que dibuja la pestaña «En vivo» de Actividad de usuarios en cada refresco.
 *
 * Fuentes: las sesiones conectadas y los minutos de hoy salen de Redis/caché
 * (tiempo real); de MySQL solo se lee una vez la ficha de los usuarios
 * (nombre y tipo), con las columnas justas.
 *
 * Con jornada, también aparecen quienes debían estar trabajando y no se han
 * conectado, y cada fila trae su uso de jornada, almuerzo y horas extra (las
 * jornadas, ausencias, guardias y almuerzos de todos se cargan en lote).
 */
final class UserActivityLiveBoard
{
    /**
     * @param  array{state?: string, type?: string, search?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, kpis: array<string, int|null>, available: bool, workday: bool}
     */
    public static function build(array $filters = [], ?int $now = null, ?LivePresenceRepository $store = null): array
    {
        $now ??= now()->getTimestamp();

        try {
            $store ??= LivePresenceStore::repository();
            $sessions = $store->online(max(60, (int) config('live-presence.session_ttl', 300)));
            $todayKey = UserActivityClock::dayKey(UserActivityClock::now());
            $todayUsers = $store->activityUsers($todayKey);
        } catch (Throwable) {
            return ['rows' => [], 'kpis' => self::emptyKpis(), 'available' => false, 'workday' => false];
        }

        $moment = CarbonImmutable::createFromTimestamp($now, UserActivityClock::timezone());
        $today = $moment->startOfDay();
        $regime = WorkdayConfig::appliesTo($today);
        $subjects = $regime ? WorkdaySubjects::all() : collect();

        $sessionsByUser = [];

        foreach ($sessions as $session) {
            $userId = (int) ($session['user_id'] ?? 0);

            if ($userId > 0 && (! isset($sessionsByUser[$userId]) || (int) ($session['last_seen'] ?? 0) > (int) ($sessionsByUser[$userId]['last_seen'] ?? 0))) {
                $sessionsByUser[$userId] = $session;
            }
        }

        $userIds = array_values(array_unique([...array_keys($sessionsByUser), ...$todayUsers, ...$subjects->keys()->map(static fn (mixed $id): int => (int) $id)->all()]));
        $missing = array_values(array_diff($userIds, $subjects->keys()->map(static fn (mixed $id): int => (int) $id)->all()));
        $users = $subjects->union($missing === [] ? [] : User::query()->whereIn('id', $missing)->get(WorkdaySubjects::columns())->keyBy('id'));
        $plans = $regime ? WorkDayPlanner::plans($subjects, $today, $today) : [];
        $lunches = $regime ? WorkBreaks::forUsers($subjects->keys()->all(), $today, $today) : [];
        $date = $today->toDateString();
        $nowMinute = UserActivityClock::minuteOfDay($moment);

        $rows = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            $session = $sessionsByUser[$userId] ?? [];
            $live = UserActivityTracker::liveState($store, $userId, $now);
            $minutes = $store->activityMinutes($userId, $todayKey);
            $summary = UserActivityMinutes::summarize($minutes);
            $profile = UserActivityProfile::classify($user);

            /** Conectado pero sin pestaña con latido nuevo (script viejo): se ve por su sesión. */
            if ($live['state'] === UserActivityState::Offline && $session !== [] && (int) ($session['last_seen'] ?? 0) >= $now - 90) {
                $live['state'] = ($session['visible'] ?? '1') === '0' ? UserActivityState::Background : UserActivityState::Active;
            }

            $plan = $plans[$userId][$date] ?? null;
            $lunch = $lunches[$userId][$date] ?? null;
            $workday = $plan !== null ? WorkdayEvaluator::evaluate($plan, $minutes, $lunch, $moment) : null;

            $rows[] = [
                'user_id' => $userId,
                'name' => (string) ($user?->name ?? $session['user_name'] ?? 'Usuario #'.$userId),
                'email' => (string) ($user?->email ?? $session['user_email'] ?? ''),
                'type' => $profile['type'],
                'type_label' => $profile['label'],
                'type_detail' => $profile['detail'],
                'state' => $live['state'],
                'since' => $live['since'],
                'last_interaction_at' => $live['last_interaction_at'],
                'tabs' => $live['tabs'],
                'panel' => $live['panel'] ?? ($session['panel_label'] ?? null),
                'page' => $live['page'] ?? ($session['page_label'] ?? null),
                'device' => self::deviceLabel($session),
                'city' => (string) ($session['city'] ?? ''),
                'last_seen' => isset($session['last_seen']) ? (int) $session['last_seen'] : null,
                'today' => [
                    'active' => $summary['active'],
                    'idle' => $summary['idle'],
                    'background' => $summary['background'],
                    'online' => $summary['online'],
                    'first' => $summary['first'],
                    'last' => $summary['last'],
                    'usage' => UserActivityMinutes::usagePercent($summary['active'], $summary['online']),
                ],
                'segments' => $workday['segments'] ?? UserActivityMinutes::segments($minutes),
                'bands' => $workday['bands'] ?? [],
                'workday' => $workday['summary'] ?? null,
                'presence' => self::presence($plan, $lunch, $live['state'], $nowMinute),
                'lunch' => $lunch,
            ];
        }

        $kpis = self::kpis($rows);

        $rows = array_values(array_filter($rows, static fn (array $row): bool => self::matches($row, $filters)));

        usort($rows, static fn (array $a, array $b): int => [$b['state']->priority(), $b['today']['active'], $a['name']] <=> [$a['state']->priority(), $a['today']['active'], $b['name']]);

        return ['rows' => $rows, 'kpis' => $kpis, 'available' => true, 'workday' => $regime];
    }

    /**
     * Situación de jornada de una persona ahora mismo, para la etiqueta y los filtros:
     * `lunch` (en almuerzo), `lunch_over` (se pasó de la hora), `absent` (debía
     * estar trabajando y no tiene el sistema abierto) o null.
     *
     * @param  array{start: int, end: int|null, reason?: string|null}|null  $lunch
     */
    private static function presence(?WorkDayPlan $plan, ?array $lunch, UserActivityState $state, int $nowMinute): ?string
    {
        if ($plan === null) {
            return null;
        }

        if ($lunch !== null && $lunch['end'] === null && $plan->lunchAllowed()) {
            return $nowMinute - $lunch['start'] >= WorkdayConfig::lunchMinutes() ? 'lunch_over' : 'lunch';
        }

        if ($state !== UserActivityState::Offline) {
            return null;
        }

        $inGuard = collect($plan->guards)->contains(static fn (array $guard): bool => ! $guard['overtime'] && $nowMinute >= $guard['from'] && $nowMinute < $guard['to']);

        return $plan->inWork($nowMinute) || $inGuard ? 'absent' : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private static function kpis(array $rows): array
    {
        $kpis = self::emptyKpis();

        foreach ($rows as $row) {
            /** Quien no se ha conectado hoy no «se fue»: se cuenta como ausente, no como desconectado. */
            if ($row['state'] !== UserActivityState::Offline || $row['today']['online'] > 0) {
                $kpis[match ($row['state']) {
                    UserActivityState::Active => 'active',
                    UserActivityState::Idle => 'idle',
                    UserActivityState::Background => 'background',
                    UserActivityState::Offline => 'offline',
                }]++;
            }

            $kpis['active_minutes'] += $row['today']['active'];
            $kpis['online_minutes'] += $row['today']['online'];

            if (in_array($row['presence'], ['lunch', 'lunch_over'], true)) {
                $kpis['lunch']++;
            }

            if ($row['presence'] === 'lunch_over') {
                $kpis['lunch_over']++;
            }

            if ($row['presence'] === 'absent') {
                $kpis['absent']++;
            }

            if ($row['workday'] !== null) {
                $kpis['work_active'] += (int) $row['workday']['work_active'];
                $kpis['work_base'] += (int) $row['workday']['base'];
                $kpis['overtime'] += (int) $row['workday']['overtime'];
            }
        }

        $kpis['connected'] = $kpis['active'] + $kpis['idle'] + $kpis['background'];
        $kpis['usage'] = UserActivityMinutes::usagePercent($kpis['active_minutes'], $kpis['online_minutes']);
        $kpis['work_usage'] = WorkdayEvaluator::usage($kpis['work_active'], $kpis['work_base']);
        $kpis['today'] = count(array_filter($rows, static fn (array $row): bool => $row['today']['online'] > 0 || $row['state'] !== UserActivityState::Offline));

        return $kpis;
    }

    /**
     * @return array<string, int>
     */
    private static function emptyKpis(): array
    {
        return [
            'active' => 0, 'idle' => 0, 'background' => 0, 'offline' => 0, 'connected' => 0, 'today' => 0, 'active_minutes' => 0, 'online_minutes' => 0, 'usage' => 0,
            'lunch' => 0, 'lunch_over' => 0, 'absent' => 0, 'work_active' => 0, 'work_base' => 0, 'work_usage' => null, 'overtime' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{state?: string, type?: string, search?: string}  $filters
     */
    private static function matches(array $row, array $filters): bool
    {
        $state = (string) ($filters['state'] ?? 'connected');

        $stateOk = match ($state) {
            'all' => true,
            'connected' => $row['state'] !== UserActivityState::Offline,
            'active' => $row['state'] === UserActivityState::Active,
            'idle' => $row['state'] === UserActivityState::Idle,
            'background' => $row['state'] === UserActivityState::Background,
            'offline' => $row['state'] === UserActivityState::Offline,
            'lunch' => in_array($row['presence'], ['lunch', 'lunch_over'], true),
            'absent' => $row['presence'] === 'absent',
            default => true,
        };

        $type = (string) ($filters['type'] ?? 'all');
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        return $stateOk
            && ($type === 'all' || $row['type'] === $type)
            && ($search === '' || str_contains(mb_strtolower($row['name'].' '.$row['email'].' '.$row['type_detail'].' '.($row['page'] ?? '')), $search));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private static function deviceLabel(array $session): string
    {
        $browser = trim((string) ($session['browser'] ?? ''));
        $os = trim((string) ($session['os'] ?? ''));

        return trim($browser.($browser !== '' && $os !== '' ? ' · ' : '').$os);
    }
}
