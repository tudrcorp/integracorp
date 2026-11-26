<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;

/**
 * Modal de «llegó una conversación»: quién la ve, qué casos muestra y el «recordar en 1 min».
 *
 * - La ve quien atiende el área del caso (departamento + permiso de Atención WhatsApp), en cualquier
 *   página de Negocios, Operaciones, Administración, Marketing o Métricas, si está Disponible u Ocupado.
 * - Muestra lo que espera a una persona: casos sin tomar del área (nuevos o que llegaron de otra área)
 *   y los que un compañero le pasó. Lo marcado para un compañero presente se respeta.
 * - Un caso sale de la modal en cuanto alguien lo toma, se cierra o se devuelve al bot.
 */
final class CrmIncomingAlerts
{
    public const SNOOZE_SECONDS = 60;

    public const MAX_ITEMS = 5;

    /**
     * Paneles donde vive la modal.
     *
     * @var list<string>
     */
    public const HOST_PANELS = ['business', 'operations', 'administration', 'marketing', 'metrics'];

    /**
     * Títulos de aviso que la modal reemplaza: ya no salen además como aviso flotante.
     *
     * @var list<string>
     */
    public const NOTICES = ['new', 'move', 'assign'];

    /**
     * Bandeja de cada panel del CRM.
     *
     * @var array<string, class-string>
     */
    private const INBOX_PAGES = [
        'business' => \App\Filament\Business\Pages\AtencionWhatsapp::class,
        'operations' => \App\Filament\Operations\Pages\AtencionWhatsapp::class,
        'administration' => \App\Filament\Administration\Pages\AtencionWhatsapp::class,
    ];

    /**
     * @var array<int, list<string>>
     */
    private static array $panelsMemo = [];

    /**
     * Bandejas que atiende el usuario: departamento del área y permiso de la página.
     *
     * @return list<string>
     */
    public static function panelsFor(User $user): array
    {
        $id = (int) $user->getKey();

        if (isset(self::$panelsMemo[$id])) {
            return self::$panelsMemo[$id];
        }

        $panels = [];

        foreach (self::INBOX_PAGES as $panel => $page) {
            if (CrmInboxAlerts::canReceive($user, CrmInboxAreas::departmentFor(CrmInboxAreas::forPanel($panel)[0] ?? null))
                && self::canOpenInbox($user, $page)) {
                $panels[] = $panel;
            }
        }

        return self::$panelsMemo[$id] = $panels;
    }

    public static function receives(?User $user): bool
    {
        return $user instanceof User && self::panelsFor($user) !== [];
    }

    public static function canSee(User $user, CrmHandoffEnvelope $envelope): bool
    {
        return in_array(CrmInboxAreas::panelFor($envelope->area), self::panelsFor($user), true);
    }

    /**
     * Casos que esperan a este usuario, del que más espera al que menos; lo que le pasaron va primero.
     *
     * Lo pospuesto por el usuario no se muestra hasta que vence; wake_at dice cuándo vence el primero.
     *
     * @return array{items: list<array<string, mixed>>, total: int, wake_at: ?int}
     */
    public static function pendingFor(User $user, ?Carbon $now = null): array
    {
        $panels = self::panelsFor($user);

        if ($panels === []) {
            return ['items' => [], 'total' => 0, 'wake_at' => null];
        }

        $userId = (int) $user->getKey();
        $now ??= Carbon::now();
        $areas = [];

        foreach ($panels as $panel) {
            $areas = [...$areas, ...CrmInboxAreas::forPanel($panel)];
        }

        $envelopes = CrmInboxQueue::queryFor($areas, in_array('business', $panels, true))
            ->whereNull('taken_at')
            ->orderByRaw('CASE WHEN assigned_to = ? THEN 0 ELSE 1 END', [$userId])
            ->orderByRaw('COALESCE(last_customer_at, accepted_at) IS NULL')
            ->orderByRaw('COALESCE(last_customer_at, accepted_at)')
            ->limit(60)
            ->get();

        $others = $envelopes->pluck('assigned_to')
            ->filter(fn (mixed $id): bool => $id !== null && (int) $id !== $userId)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()->values()->all();
        $states = CrmInboxPresence::effective($others);

        $mine = $envelopes->filter(function (CrmHandoffEnvelope $envelope) use ($userId, $states): bool {
            if ($envelope->assigned_to === null || (int) $envelope->assigned_to === $userId) {
                return true;
            }

            return in_array($states[(int) $envelope->assigned_to] ?? CrmInboxPresence::AVAILABLE, [CrmInboxPresence::AWAY, CrmInboxPresence::OFFLINE], true);
        })->values();

        $snoozed = self::snoozed($userId, $mine->map(fn (CrmHandoffEnvelope $envelope): string => (string) $envelope->handoff_id)->all());
        $mine = $mine->reject(fn (CrmHandoffEnvelope $envelope): bool => isset($snoozed[(string) $envelope->handoff_id]))->values();
        $shown = $mine->take(self::MAX_ITEMS);
        $names = self::names($shown->flatMap(fn (CrmHandoffEnvelope $envelope): array => array_filter([$envelope->assigned_by, $envelope->moved_by]))->all());

        return [
            'items' => $shown->map(fn (CrmHandoffEnvelope $envelope): array => self::item($envelope, $userId, $names, $now))->all(),
            'total' => $mine->count(),
            'wake_at' => $snoozed !== [] ? min($snoozed) : null,
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    public static function item(CrmHandoffEnvelope $envelope, int $userId, array $names, Carbon $now): array
    {
        $row = CrmInboxCase::row($envelope, $now);
        $kind = match (true) {
            $envelope->assigned_to !== null && (int) $envelope->assigned_to === $userId => 'assign',
            $envelope->moved_at !== null => 'move',
            default => 'new',
        };
        $by = match ($kind) {
            'assign' => $names[(int) $envelope->assigned_by] ?? null,
            'move' => $names[(int) $envelope->moved_by] ?? null,
            default => null,
        };
        $motivo = trim((string) $envelope->motivo);
        $panel = CrmInboxAreas::panelFor($envelope->area);

        return [
            'id' => (string) $envelope->handoff_id,
            'kind' => $kind,
            'title' => CrmInboxAlerts::TITLES[$kind],
            'by' => $by,
            'name' => $row['name'],
            'initials' => self::initials($row['name']),
            'phone' => $row['phone_label'],
            'area' => $row['area_label'],
            'motivo' => $motivo !== '' ? (mb_strlen($motivo) > 90 ? rtrim(mb_substr($motivo, 0, 87)).'…' : $motivo) : null,
            'last_line' => $row['last_line'] !== $motivo ? $row['last_line'] : null,
            'since' => $row['waiting_since'],
            'panel' => $panel,
            'url' => self::inboxUrl($panel, (string) $envelope->handoff_id),
        ];
    }

    public static function inboxUrl(string $panel, string $handoffId): string
    {
        $page = self::INBOX_PAGES[$panel] ?? self::INBOX_PAGES['business'];

        return $page::getUrl(['caso' => $handoffId], panel: $panel);
    }

    /**
     * Pospone estos casos un minuto para el usuario (en todas sus pestañas).
     *
     * @param  list<string>  $handoffIds
     */
    public static function snooze(int $userId, array $handoffIds, int $seconds = self::SNOOZE_SECONDS): void
    {
        $handoffIds = array_values(array_filter($handoffIds, fn (mixed $id): bool => is_string($id) && preg_match('/^\d{1,18}$/', $id) === 1));

        if ($handoffIds === []) {
            return;
        }

        $until = time() + $seconds;

        CrmInboxCache::attempt(static function (Repository $store) use ($userId, $handoffIds, $until, $seconds): void {
            $values = [];

            foreach ($handoffIds as $id) {
                $values[self::snoozeKey($userId, $id)] = $until;
            }

            $store->putMany($values, $seconds);
        });
    }

    /**
     * Pospuestos vigentes del usuario entre estos casos: handoff_id => hasta cuándo (timestamp).
     *
     * @param  list<string>  $handoffIds
     * @return array<string, int>
     */
    public static function snoozed(int $userId, array $handoffIds): array
    {
        if ($handoffIds === []) {
            return [];
        }

        $keys = [];

        foreach ($handoffIds as $id) {
            $keys[self::snoozeKey($userId, (string) $id)] = (string) $id;
        }

        $values = CrmInboxCache::attempt(static fn (Repository $store): array => $store->many(array_keys($keys)), []);
        $now = time();
        $result = [];

        foreach (is_array($values) ? $values : [] as $key => $until) {
            if (isset($keys[$key]) && is_numeric($until) && (int) $until > $now) {
                $result[$keys[$key]] = (int) $until;
            }
        }

        return $result;
    }

    public static function forgetMemo(): void
    {
        self::$panelsMemo = [];
    }

    private static function snoozeKey(int $userId, string $handoffId): string
    {
        return 'crm-alert-snooze:'.$userId.':'.$handoffId;
    }

    /**
     * @param  class-string  $page
     */
    private static function canOpenInbox(User $user, string $page): bool
    {
        if (DepartmentNavigationPermissionRegistry::isSuperAdminOnly($page)) {
            return UserNavigationAccess::isSuperAdmin($user);
        }

        $module = DepartmentNavigationPermissionRegistry::moduleFor($page);

        if ($module === null) {
            return true;
        }

        return UserNavigationAccess::canAccessMenuItem($user, $module, DepartmentNavigationPermissionRegistry::slugsFor($page));
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    private static function names(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn (mixed $id): bool => is_numeric($id)))));

        if ($ids === []) {
            return [];
        }

        return User::query()->whereKey($ids)->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => trim((string) $name)])
            ->all();
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice(array_values(array_filter($words)), 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters !== '' && $name !== 'Sin nombre' ? $letters : '?';
    }
}
