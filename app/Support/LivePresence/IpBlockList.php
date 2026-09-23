<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\SecurityIpBlock;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Lista negra de IPs.
 *
 * La fuente de verdad es `security_ip_blocks` (duradera y auditada). Como se
 * revisa en todas las peticiones, antes de sesión y autenticación, se guarda un
 * mapa IP → vencimiento en la caché (Redis en producción) que se reconstruye al
 * bloquear o levantar y, como respaldo, cada 5 minutos.
 *
 * Si la caché y la base fallan, la IP pasa: la disponibilidad del sistema pesa
 * más que un bloqueo. Una IP de confianza nunca se bloquea, aunque figure aquí.
 */
final class IpBlockList
{
    public const MIN_REASON_LENGTH = 10;

    private const CACHE_KEY = 'live-presence:blocked-ips';

    private const CACHE_TTL = 300;

    /**
     * @var array<string, int|null>|null
     */
    private static ?array $memo = null;

    public static function isBlocked(string $ip): bool
    {
        if ($ip === '' || SecurityMonitor::isTrusted($ip)) {
            return false;
        }

        $map = self::map();

        if ($map === []) {
            return false;
        }

        $now = time();

        if (array_key_exists($ip, $map)) {
            return $map[$ip] === null || $map[$ip] > $now;
        }

        foreach ($map as $entry => $expires) {
            if (str_contains((string) $entry, '/') && ($expires === null || $expires > $now) && self::matches($ip, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Por qué no se puede bloquear esta IP, o null si se puede.
     */
    public static function restrictionFor(string $ip, ?Authenticatable $actor = null, ?string $actorIp = null): ?string
    {
        $actor ??= Auth::user();
        $actorIp ??= app()->runningInConsole() ? null : ClientLocation::ip(request());

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'La IP no es válida.';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Es una IP privada o reservada (red interna o del servidor): bloquearla puede dejar fuera a todo el sistema.';
        }

        if (SecurityMonitor::isTrusted($ip)) {
            return 'Está en la lista de IPs de confianza (LIVE_SECURITY_TRUSTED_IPS).';
        }

        if ($actorIp !== null && $actorIp === $ip) {
            return 'Es su propia IP: se quedaría sin acceso al sistema.';
        }

        $sessions = self::sessionsFrom($ip);
        $allowedEmails = (array) config('live-presence.allowed_emails', []);

        /** Sin consultar la base: quien bloquea o un usuario del monitor conectado desde la IP. */
        foreach ($sessions as $session) {
            if (($actor !== null && (int) $actor->getAuthIdentifier() === $session['user_id'])
                || in_array(mb_strtolower($session['user_email']), $allowedEmails, true)) {
                return 'Desde esta IP está conectado '.$session['user_name'].' (usted, un SUPERADMIN o un usuario del monitor): no se puede bloquear desde aquí.';
            }
        }

        foreach (self::usersFor(array_column($sessions, 'user_id')) as $user) {
            if (UserNavigationAccess::isSuperAdmin($user) || LivePresenceAccess::allows($user)) {
                return 'Desde esta IP está conectado '.$user->name.' (usted, un SUPERADMIN o un usuario del monitor): no se puede bloquear desde aquí.';
            }
        }

        return null;
    }

    /**
     * Sesiones abiertas ahora mismo desde la IP: quiénes quedarían fuera.
     *
     * @return list<array{user_id: int, user_name: string, user_email: string}>
     */
    public static function sessionsFrom(string $ip): array
    {
        try {
            $rows = LivePresenceStore::repository()->online(max(60, (int) config('live-presence.session_ttl', 300)));
        } catch (Throwable) {
            return [];
        }

        $users = [];

        foreach ($rows as $row) {
            if ((string) ($row['ip'] ?? '') === $ip && (int) ($row['user_id'] ?? 0) > 0) {
                $users[(int) $row['user_id']] = [
                    'user_id' => (int) $row['user_id'],
                    'user_name' => (string) ($row['user_name'] ?? 'Usuario'),
                    'user_email' => (string) ($row['user_email'] ?? ''),
                ];
            }
        }

        return array_values($users);
    }

    /**
     * @param  array<string, mixed>  $evidence  lo que el monitor veía de la IP al bloquearla
     *
     * @throws InvalidArgumentException
     */
    public static function block(string $ip, string $reason, ?int $minutes, ?string $verdict = null, array $evidence = [], ?Authenticatable $actor = null, ?string $actorIp = null): SecurityIpBlock
    {
        $actor ??= Auth::user();
        $ip = trim($ip);
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Explique el motivo del bloqueo (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        $restriction = self::restrictionFor($ip, $actor, $actorIp);

        if ($restriction !== null) {
            throw new InvalidArgumentException($restriction);
        }

        if ($minutes !== null && ($minutes < 1 || $minutes > 525600)) {
            throw new InvalidArgumentException('La duración del bloqueo no es válida.');
        }

        if ($verdict !== null && ! array_key_exists($verdict, IpThreatAssessment::LABELS)) {
            $verdict = null;
        }

        $block = DB::transaction(function () use ($ip, $reason, $minutes, $verdict, $evidence, $actor, $actorIp): SecurityIpBlock {
            SecurityIpBlock::query()->where('ip', $ip)->active()->lockForUpdate()->get()
                ->each(function (SecurityIpBlock $previous) use ($actor): void {
                    $previous->forceFill([
                        'lifted_at' => now(),
                        'lifted_by_id' => $actor?->getAuthIdentifier(),
                        'lifted_by_name' => (string) ($actor->name ?? 'system'),
                        'lift_reason' => 'Reemplazado por un bloqueo nuevo.',
                    ])->save();
                });

            return SecurityIpBlock::query()->create([
                'ip' => $ip,
                'verdict' => $verdict,
                'reason' => $reason,
                'evidence' => $evidence === [] ? null : $evidence,
                'expires_at' => $minutes === null ? null : now()->addMinutes($minutes),
                'blocked_by_id' => $actor?->getAuthIdentifier(),
                'blocked_by_name' => (string) ($actor->name ?? 'system'),
                'blocked_from_ip' => $actorIp ?? (app()->runningInConsole() ? null : ClientLocation::ip(request())),
            ]);
        });

        self::refresh();
        SecurityMonitor::undismissIp($ip);

        SecurityAudit::log('AUDIT_LIVE_SECURITY_IP_BLOCKED', 'live-presence.ip-block', [
            'block_id' => $block->getKey(),
            'ip' => $ip,
            'verdict' => $verdict,
            'reason' => $reason,
            'expires_at' => $block->expires_at?->toIso8601String(),
        ]);

        return $block;
    }

    public static function lift(SecurityIpBlock $block, string $reason, ?Authenticatable $actor = null): void
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

        SecurityAudit::log('AUDIT_LIVE_SECURITY_IP_UNBLOCKED', 'live-presence.ip-block', [
            'block_id' => $block->getKey(),
            'ip' => $block->ip,
            'reason' => $reason,
        ]);
    }

    /**
     * @return list<SecurityIpBlock>
     */
    public static function active(): array
    {
        try {
            return SecurityIpBlock::query()->active()->latest('id')->limit(200)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * IPs bloqueadas ahora mismo, desde la caché (sin consultar MySQL).
     *
     * @return list<string>
     */
    public static function activeIps(): array
    {
        $now = time();

        return array_map('strval', array_keys(array_filter(self::map(), static fn (?int $expires): bool => $expires === null || $expires > $now)));
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

    private static function matches(string $ip, string $range): bool
    {
        try {
            return IpUtils::checkIp($ip, $range);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<User>
     */
    private static function usersFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            return User::query()->whereIn('id', $ids)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, int|null> IP o rango → vencimiento (null = hasta levantarlo)
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
     * @return array<string, int|null>
     */
    private static function loadFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('security_ip_blocks')) {
                return [];
            }

            $map = [];

            foreach (SecurityIpBlock::query()->active()->get(['ip', 'expires_at']) as $block) {
                $expires = $block->expires_at?->getTimestamp();
                $current = $map[$block->ip] ?? false;

                /** Si hay dos bloqueos vigentes de la misma IP, gana el más largo. */
                $map[$block->ip] = $current === false ? $expires : (($current === null || $expires === null) ? null : max($current, $expires));
            }

            return $map;
        } catch (Throwable) {
            return [];
        }
    }
}
