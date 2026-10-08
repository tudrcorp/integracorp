<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\User;
use App\Support\LivePresence\LivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use Throwable;

/**
 * Lo que dibuja la pestaña «En vivo» de Actividad de usuarios en cada refresco.
 *
 * Fuentes: las sesiones conectadas y los minutos de hoy salen de Redis/caché
 * (tiempo real); de MySQL solo se lee una vez la ficha de los usuarios
 * (nombre y tipo), con las columnas justas.
 */
final class UserActivityLiveBoard
{
    /**
     * @param  array{state?: string, type?: string, search?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, kpis: array<string, int>, available: bool}
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
            return ['rows' => [], 'kpis' => self::emptyKpis(), 'available' => false];
        }

        $sessionsByUser = [];

        foreach ($sessions as $session) {
            $userId = (int) ($session['user_id'] ?? 0);

            if ($userId > 0 && (! isset($sessionsByUser[$userId]) || (int) ($session['last_seen'] ?? 0) > (int) ($sessionsByUser[$userId]['last_seen'] ?? 0))) {
                $sessionsByUser[$userId] = $session;
            }
        }

        $userIds = array_values(array_unique([...array_keys($sessionsByUser), ...$todayUsers]));
        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get(UserActivityProfile::COLUMNS)->keyBy('id');

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
                'segments' => UserActivityMinutes::segments($minutes),
            ];
        }

        $kpis = self::kpis($rows);

        $rows = array_values(array_filter($rows, static fn (array $row): bool => self::matches($row, $filters)));

        usort($rows, static fn (array $a, array $b): int => [$b['state']->priority(), $b['today']['active'], $a['name']] <=> [$a['state']->priority(), $a['today']['active'], $b['name']]);

        return ['rows' => $rows, 'kpis' => $kpis, 'available' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private static function kpis(array $rows): array
    {
        $kpis = self::emptyKpis();

        foreach ($rows as $row) {
            $kpis[match ($row['state']) {
                UserActivityState::Active => 'active',
                UserActivityState::Idle => 'idle',
                UserActivityState::Background => 'background',
                UserActivityState::Offline => 'offline',
            }]++;

            $kpis['active_minutes'] += $row['today']['active'];
            $kpis['online_minutes'] += $row['today']['online'];
        }

        $kpis['connected'] = $kpis['active'] + $kpis['idle'] + $kpis['background'];
        $kpis['usage'] = UserActivityMinutes::usagePercent($kpis['active_minutes'], $kpis['online_minutes']);
        $kpis['today'] = count($rows);

        return $kpis;
    }

    /**
     * @return array<string, int>
     */
    private static function emptyKpis(): array
    {
        return ['active' => 0, 'idle' => 0, 'background' => 0, 'offline' => 0, 'connected' => 0, 'today' => 0, 'active_minutes' => 0, 'online_minutes' => 0, 'usage' => 0];
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
