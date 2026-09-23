<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\SecurityUserBlock;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Lista negra de usuarios.
 *
 * La fuente de verdad es `security_user_blocks` (duradera y auditada). Para
 * revisarla en cada petición sin consultar MySQL se guarda un mapa
 * usuario → vencimiento en la caché (Redis en producción), que se reconstruye
 * al bloquear o levantar y, como respaldo, cada 5 minutos.
 *
 * El bloqueo no toca `users.status`: los flujos de agentes, comisiones y
 * cotizaciones siguen intactos, solo se impide el acceso.
 */
final class UserBlockList
{
    public const MIN_REASON_LENGTH = 10;

    private const CACHE_KEY = 'live-presence:blocked-users';

    private const CACHE_TTL = 300;

    /**
     * @var array<int, int|null>|null
     */
    private static ?array $memo = null;

    public static function isBlocked(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $map = self::map();

        if (! array_key_exists($userId, $map)) {
            return false;
        }

        $expires = $map[$userId];

        return $expires === null || $expires > time();
    }

    /**
     * Por qué no se puede bloquear a este usuario, o null si se puede.
     */
    public static function restrictionFor(User $user, ?Authenticatable $actor = null): ?string
    {
        $actor ??= Auth::user();

        if ($actor !== null && (int) $actor->getAuthIdentifier() === (int) $user->getKey()) {
            return 'No puede bloquearse a sí mismo.';
        }

        if (UserNavigationAccess::isSuperAdmin($user) || LivePresenceAccess::allows($user)) {
            return 'Los usuarios SUPERADMIN y los del monitor no se pueden bloquear desde aquí.';
        }

        return null;
    }

    /**
     * Usuarios bloqueados ahora mismo, desde la caché (sin consultar MySQL).
     */
    public static function activeCount(): int
    {
        $now = time();

        return count(array_filter(self::map(), static fn (?int $expires): bool => $expires === null || $expires > $now));
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function block(User $user, string $reason, ?int $minutes, ?Authenticatable $actor = null): SecurityUserBlock
    {
        $actor ??= Auth::user();
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Explique el motivo del bloqueo (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        $restriction = self::restrictionFor($user, $actor);

        if ($restriction !== null) {
            throw new InvalidArgumentException($restriction);
        }

        if ($minutes !== null && ($minutes < 1 || $minutes > 525600)) {
            throw new InvalidArgumentException('La duración del bloqueo no es válida.');
        }

        $block = DB::transaction(function () use ($user, $reason, $minutes, $actor): SecurityUserBlock {
            SecurityUserBlock::query()->where('user_id', $user->getKey())->active()->lockForUpdate()->get()
                ->each(function (SecurityUserBlock $previous) use ($actor): void {
                    $previous->forceFill([
                        'lifted_at' => now(),
                        'lifted_by_id' => $actor?->getAuthIdentifier(),
                        'lifted_by_name' => (string) ($actor->name ?? 'system'),
                        'lift_reason' => 'Reemplazado por un bloqueo nuevo.',
                    ])->save();
                });

            return SecurityUserBlock::query()->create([
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
                'user_email' => $user->email,
                'reason' => $reason,
                'expires_at' => $minutes === null ? null : now()->addMinutes($minutes),
                'blocked_by_id' => $actor?->getAuthIdentifier(),
                'blocked_by_name' => (string) ($actor->name ?? 'system'),
                'blocked_from_ip' => app()->runningInConsole() ? null : ClientLocation::ip(request()),
            ]);
        });

        self::refresh();

        SecurityAudit::log('AUDIT_LIVE_SECURITY_USER_BLOCKED', 'live-presence.user-block', [
            'block_id' => $block->getKey(),
            'user_id' => $user->getKey(),
            'user_email' => $user->email,
            'reason' => $reason,
            'expires_at' => $block->expires_at?->toIso8601String(),
        ]);

        return $block;
    }

    public static function lift(SecurityUserBlock $block, string $reason, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        if ($block->lifted_at !== null) {
            return;
        }

        $block->forceFill([
            'lifted_at' => now(),
            'lifted_by_id' => $actor?->getAuthIdentifier(),
            'lifted_by_name' => (string) ($actor->name ?? 'system'),
            'lift_reason' => trim($reason) !== '' ? trim($reason) : null,
        ])->save();

        self::refresh();

        SecurityAudit::log('AUDIT_LIVE_SECURITY_USER_UNBLOCKED', 'live-presence.user-block', [
            'block_id' => $block->getKey(),
            'user_id' => $block->user_id,
            'reason' => $reason,
        ]);
    }

    /**
     * @return list<SecurityUserBlock>
     */
    public static function active(): array
    {
        try {
            return SecurityUserBlock::query()->active()->latest('id')->limit(100)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function refresh(): void
    {
        self::$memo = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }

        self::map();
    }

    /**
     * Para pruebas.
     */
    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    /**
     * @return array<int, int|null> usuario → vencimiento (null = permanente)
     */
    private static function map(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $map = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static fn (): array => self::loadFromDatabase());
        } catch (Throwable) {
            $map = self::loadFromDatabase();
        }

        return self::$memo = is_array($map) ? $map : [];
    }

    /**
     * @return array<int, int|null>
     */
    private static function loadFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('security_user_blocks')) {
                return [];
            }

            return SecurityUserBlock::query()
                ->active()
                ->get(['user_id', 'expires_at'])
                ->mapWithKeys(fn (SecurityUserBlock $block): array => [(int) $block->user_id => $block->expires_at?->getTimestamp()])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
