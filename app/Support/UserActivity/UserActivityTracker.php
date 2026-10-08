<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Support\LivePresence\LivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Traduce latidos y peticiones a uso del sistema.
 *
 * - Cada latido marca el minuto actual del usuario como activo, inactivo o en
 *   otra pestaña. Si entre dos latidos de la misma pestaña hubo un hueco corto
 *   (pestaña oculta late cada 60 s), se rellena con el estado anterior.
 * - Los cambios de estado (quedó inactivo, volvió a trabajar) y cada paso del
 *   recorrido van a una cola que el job de volcado guarda en la base.
 * - El estado en vivo se lleva por pestaña: el usuario está «activo» si
 *   cualquiera de sus pestañas lo está.
 *
 * Nada de esto toca MySQL en la petición: todo va a Redis o a la caché, y un
 * fallo del almacén nunca rompe la navegación (`LivePresenceStore::safely`).
 */
final class UserActivityTracker
{
    private const TAB_PREFIX = 'ua:tab:';

    private const LIVE_PREFIX = 'ua:live:';

    /** Una pestaña sin latido en este tiempo deja de contar para el estado en vivo. */
    public const TAB_STALE_SECONDS = 150;

    public static function enabled(): bool
    {
        return (bool) config('live-presence.enabled', true) && (bool) config('live-presence.activity.enabled', true);
    }

    public static function idleAfterSeconds(): int
    {
        return max(60, (int) config('live-presence.activity.idle_after_seconds', 300));
    }

    /**
     * Estado que reporta una pestaña. Sin `idle` (script viejo aún abierto) una
     * pestaña visible cuenta como activa, igual que antes.
     */
    public static function stateFor(bool $visible, ?int $idleMs): UserActivityState
    {
        if (! $visible) {
            return UserActivityState::Background;
        }

        if ($idleMs !== null && $idleMs >= self::idleAfterSeconds() * 1000) {
            return UserActivityState::Idle;
        }

        return UserActivityState::Active;
    }

    /**
     * @param  array{panel?: string|null, page?: string|null, path?: string|null}  $context
     */
    public static function recordPing(int $userId, ?string $tabId, bool $visible, ?int $idleMs, string $reason, array $context = [], ?int $now = null): void
    {
        if (! self::enabled() || $userId <= 0) {
            return;
        }

        $now ??= now()->getTimestamp();
        $state = self::stateFor($visible, $idleMs);
        $tabId = self::normalizeTab($tabId);
        $nowMinute = intdiv($now, 60);
        $idleSeconds = $idleMs === null ? 0 : intdiv(max(0, $idleMs), 1000);
        $lastInteractionAt = $visible ? $now - $idleSeconds : null;

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($userId, $tabId, $state, $now, $nowMinute, $reason, $context, $lastInteractionAt, $idleSeconds): void {
            $tabKey = self::TAB_PREFIX.$userId.':'.$tabId;
            $previous = $store->getValue($tabKey);
            $previousState = is_array($previous) ? UserActivityState::tryFrom((string) ($previous['s'] ?? '')) : null;
            $previousMinute = is_array($previous) ? (int) ($previous['m'] ?? 0) : 0;

            self::markMinutes($store, $userId, [$nowMinute], $state);

            $gap = $nowMinute - $previousMinute;

            if ($previousState !== null && $gap > 1 && $gap <= self::maxGapMinutes()) {
                self::markMinutes($store, $userId, range($previousMinute + 1, $nowMinute - 1), $previousState);
            }

            $store->putValue($tabKey, ['m' => $nowMinute, 's' => $state->value, 'at' => $now], 900);

            $events = [];

            if ($reason === 'visibility') {
                $events[] = self::event($userId, $now, 'visibility', $state === UserActivityState::Background ? 'Dejó el sistema en otra pestaña' : 'Volvió a la pestaña del sistema', $context);
            } elseif ($previousState !== null && $previousState !== $state) {
                if ($state === UserActivityState::Idle) {
                    $events[] = self::event($userId, max($now - $idleSeconds, $previousMinute * 60), 'idle', 'Quedó inactivo: sin teclado ni mouse por '.intdiv(self::idleAfterSeconds(), 60).' min', $context);
                } elseif ($state === UserActivityState::Active && $previousState === UserActivityState::Idle) {
                    $events[] = self::event($userId, $now, 'active', 'Volvió a usar el sistema', $context);
                }
            }

            if ($events !== []) {
                $store->appendActivityEvents($events, self::bufferTtl());
            }

            self::updateLiveState($store, $userId, $tabId, [
                's' => $state->value,
                'at' => $now,
                'li' => $lastInteractionAt,
                'panel' => $context['panel'] ?? null,
                'page' => $context['page'] ?? null,
            ], $previousState !== $state ? $now : null);
        });
    }

    /**
     * Un paso del recorrido que ve el servidor (página, acción, descarga). Las
     * páginas y acciones también cuentan como minuto activo: vienen de un clic.
     *
     * @param  array{type: string, label: string, panel?: string|null, page?: string|null, path?: string|null, ms?: int|null, status?: int|null}  $step
     */
    public static function recordStep(int $userId, array $step, ?int $now = null): void
    {
        if (! self::enabled() || $userId <= 0 || trim((string) ($step['label'] ?? '')) === '') {
            return;
        }

        $now ??= now()->getTimestamp();

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($userId, $step, $now): void {
            if (in_array($step['type'], ['page', 'action'], true)) {
                self::markMinutes($store, $userId, [intdiv($now, 60)], UserActivityState::Active);
            }

            $store->appendActivityEvents([
                self::event($userId, $now, (string) $step['type'], (string) $step['label'], [
                    'panel' => $step['panel'] ?? null,
                    'page' => $step['page'] ?? null,
                    'path' => $step['path'] ?? null,
                ], $step['ms'] ?? null, $step['status'] ?? null),
            ], self::bufferTtl());
        });
    }

    /**
     * Estado en vivo del usuario: el mejor de sus pestañas que siguen latiendo.
     *
     * @return array{state: UserActivityState, since: int|null, last_interaction_at: int|null, tabs: int, panel: string|null, page: string|null}
     */
    public static function liveState(LivePresenceRepository $store, int $userId, ?int $now = null): array
    {
        $now ??= now()->getTimestamp();
        $value = $store->getValue(self::LIVE_PREFIX.$userId);
        $tabs = is_array($value['tabs'] ?? null) ? $value['tabs'] : [];
        $best = null;
        $lastInteraction = null;
        $alive = 0;

        foreach ($tabs as $tab) {
            if (! is_array($tab) || (int) ($tab['at'] ?? 0) < $now - self::TAB_STALE_SECONDS) {
                continue;
            }

            $alive++;
            $state = UserActivityState::fromCode($tab['s'] ?? null);

            if (isset($tab['li']) && is_numeric($tab['li'])) {
                $lastInteraction = max($lastInteraction ?? 0, (int) $tab['li']);
            }

            if ($best === null || $state->priority() > $best['state']->priority()) {
                $best = ['state' => $state, 'tab' => $tab];
            }
        }

        if ($best === null) {
            return ['state' => UserActivityState::Offline, 'since' => null, 'last_interaction_at' => null, 'tabs' => 0, 'panel' => null, 'page' => null];
        }

        $since = match ($best['state']) {
            UserActivityState::Idle => $lastInteraction !== null ? $lastInteraction + self::idleAfterSeconds() : ($value['since'] ?? null),
            default => isset($value['since']) ? (int) $value['since'] : null,
        };

        return [
            'state' => $best['state'],
            'since' => $since !== null ? (int) $since : null,
            'last_interaction_at' => $lastInteraction,
            'tabs' => $alive,
            'panel' => $best['tab']['panel'] ?? null,
            'page' => $best['tab']['page'] ?? null,
        ];
    }

    /**
     * @param  list<int>  $epochMinutes
     */
    private static function markMinutes(LivePresenceRepository $store, int $userId, array $epochMinutes, UserActivityState $state): void
    {
        if ($state === UserActivityState::Offline || $epochMinutes === []) {
            return;
        }

        /** Las claves `Ymd` son numéricas y PHP las vuelve enteros: se devuelven a texto. */
        foreach (UserActivityClock::splitByDay($epochMinutes) as $day => $minutes) {
            $store->markActivityMinutes($userId, (string) $day, $minutes, $state->value, self::bufferTtl());
        }
    }

    /**
     * @param  array<string, mixed>  $tab
     */
    private static function updateLiveState(LivePresenceRepository $store, int $userId, string $tabId, array $tab, ?int $tabChangedAt): void
    {
        $key = self::LIVE_PREFIX.$userId;
        $now = (int) $tab['at'];
        $value = $store->getValue($key) ?? [];
        $tabs = is_array($value['tabs'] ?? null) ? $value['tabs'] : [];

        $before = self::bestCode($tabs, $now);
        $tabs[$tabId] = $tab;
        $tabs = array_filter($tabs, static fn (mixed $entry): bool => is_array($entry) && (int) ($entry['at'] ?? 0) >= $now - self::TAB_STALE_SECONDS);
        $after = self::bestCode($tabs, $now);

        $since = isset($value['since']) ? (int) $value['since'] : $now;

        if ($before !== $after) {
            $since = $tabChangedAt ?? $now;
        }

        $store->putValue($key, ['tabs' => $tabs, 'since' => $since], 600);
    }

    /**
     * @param  array<string, mixed>  $tabs
     */
    private static function bestCode(array $tabs, int $now): string
    {
        $best = UserActivityState::Offline;

        foreach ($tabs as $tab) {
            if (! is_array($tab) || (int) ($tab['at'] ?? 0) < $now - self::TAB_STALE_SECONDS) {
                continue;
            }

            $state = UserActivityState::fromCode($tab['s'] ?? null);

            if ($state->priority() > $best->priority()) {
                $best = $state;
            }
        }

        return $best->value;
    }

    /**
     * @param  array{panel?: string|null, page?: string|null, path?: string|null}  $context
     * @return array<string, mixed>
     */
    private static function event(int $userId, int $at, string $type, string $label, array $context, ?int $ms = null, ?int $status = null): array
    {
        return [
            'event_key' => (string) Str::uuid(),
            'user_id' => $userId,
            'occurred_at' => CarbonImmutable::createFromTimestamp($at, UserActivityClock::timezone())->format('Y-m-d H:i:s'),
            'type' => Str::limit($type, 20, ''),
            'label' => Str::limit(trim($label), 250, '…'),
            'panel' => self::limitOrNull($context['panel'] ?? null, 60),
            'page' => self::limitOrNull($context['page'] ?? null, 250),
            'path' => self::limitOrNull($context['path'] ?? null, 300),
            'duration_ms' => $ms !== null ? max(0, $ms) : null,
            'status' => $status !== null && $status > 0 && $status < 1000 ? $status : null,
        ];
    }

    private static function limitOrNull(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    private static function normalizeTab(?string $tabId): string
    {
        $tabId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $tabId) ?? '';

        return $tabId === '' ? 'legacy' : substr($tabId, 0, 40);
    }

    private static function maxGapMinutes(): int
    {
        return max(1, (int) config('live-presence.activity.max_gap_minutes', 3));
    }

    private static function bufferTtl(): int
    {
        return max(3600, (int) config('live-presence.activity.buffer_ttl', 259200));
    }
}
