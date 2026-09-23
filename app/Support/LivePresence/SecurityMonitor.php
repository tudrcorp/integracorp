<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Detección de ataques en tiempo real para el monitor.
 *
 * Cada petición y cada login fallido suman contadores por ventana de tiempo en
 * Redis (o la caché en desarrollo). Al cruzar un umbral se emite un evento de
 * seguridad una sola vez por ventana, se puntúa a la IP en el ranking de
 * sospechosas y, si es crítico, se avisa por WhatsApp y correo con pausa.
 *
 * Contra la fuerza bruta protege a la víctima: tras N fallos la cuenta atacada
 * no admite login por unos minutos, aunque el atacante cambie de IP.
 */
final class SecurityMonitor
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /** Métricas globales por minuto que se grafican en el monitor. */
    public const METRICS = ['requests', 'anonymous', 'failed_logins', 'not_found', 'csrf', 'throttled', 'forbidden', 'server_errors', 'scanner', 'bot'];

    private const PREFIX = 'lp:sec:';

    private const DAY = 86400;

    public static function recordRequest(Request $request, ?Response $response, bool $authenticated): void
    {
        if (self::isMonitorTraffic($request)) {
            return;
        }

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($request, $response, $authenticated): void {
            $ip = ClientLocation::ip($request);
            $trusted = self::isTrusted($ip);
            $minute = self::minute();
            $status = $response?->getStatusCode() ?? 200;
            $thresholds = self::thresholds();

            self::bump($store, 'requests', $minute);

            if (! $authenticated) {
                self::bump($store, 'anonymous', $minute);
            }

            $metric = match (true) {
                $status === 404 => 'not_found',
                $status === 419 => 'csrf',
                $status === 429 => 'throttled',
                $status === 403 => 'forbidden',
                $status >= 500 => 'server_errors',
                default => null,
            };

            if ($metric !== null) {
                self::bump($store, $metric, $minute);
            }

            if ($trusted) {
                return;
            }

            $requestsThisMinute = $store->increment(self::PREFIX.'ip:'.$ip.':rpm:'.$minute, 120);

            if ($requestsThisMinute >= $thresholds['requests_per_ip_minute']) {
                self::flagIp($store, $request, $ip, 'bot', 5);
                self::emit($store, 'flood:'.$ip, 120, [
                    'type' => 'flood',
                    'severity' => self::SEVERITY_CRITICAL,
                    'title' => 'Inundación de peticiones',
                    'detail' => $requestsThisMinute.' peticiones en un minuto desde '.$ip.'.',
                    'ip' => $ip,
                ]);
            }

            if ($status === 404) {
                $notFound = $store->increment(self::PREFIX.'ip:'.$ip.':404:'.$minute, 120);
                $store->scoreMember(self::PREFIX.'offenders', $ip, 0.2, self::DAY);

                if ($notFound >= $thresholds['not_found_per_ip_minute']) {
                    self::flagIp($store, $request, $ip, 'escáner', 3);
                    self::emit($store, 'scan404:'.$ip, 600, [
                        'type' => 'scanner',
                        'severity' => self::SEVERITY_WARNING,
                        'title' => 'Ráfaga de páginas inexistentes',
                        'detail' => $notFound.' respuestas 404 en un minuto desde '.$ip.'.',
                        'ip' => $ip,
                    ]);
                }
            }

            if (in_array($status, [419, 429], true)) {
                $store->scoreMember(self::PREFIX.'offenders', $ip, 0.5, self::DAY);
            }

            $scannerPath = self::scannerPathHit($request);

            if ($scannerPath !== null) {
                self::bump($store, 'scanner', $minute);
                self::flagIp($store, $request, $ip, 'escáner', 5);
                self::emit($store, 'scanpath:'.$ip, 600, [
                    'type' => 'scanner',
                    'severity' => self::SEVERITY_WARNING,
                    'title' => 'Escáner buscando fallas',
                    'detail' => 'Pidió «'.$scannerPath.'» desde '.$ip.'.',
                    'ip' => $ip,
                ]);
            }

            if (! $authenticated && self::isBotAgent((string) $request->userAgent())) {
                self::bump($store, 'bot', $minute);
                self::flagIp($store, $request, $ip, 'bot', 1);
                self::emit($store, 'botua:'.$ip, 1800, [
                    'type' => 'bot',
                    'severity' => self::SEVERITY_WARNING,
                    'title' => 'Cliente automatizado',
                    'detail' => 'User-Agent «'.Str::limit((string) $request->userAgent(), 60).'» desde '.$ip.'.',
                    'ip' => $ip,
                ]);
            }
        });
    }

    /**
     * Login fallido en cualquier panel o en la PWA.
     */
    public static function recordFailedLogin(Request $request, string $identifier, string $channel): void
    {
        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($request, $identifier, $channel): void {
            $ip = ClientLocation::ip($request);
            $account = self::normalizeAccount($identifier);
            $accountKey = self::accountKey($account);
            $thresholds = self::thresholds();
            $trusted = self::isTrusted($ip);

            self::bump($store, 'failed_logins', self::minute());

            $store->scoreMember(self::PREFIX.'targets', $account, 1, self::DAY);
            $store->pushList(self::PREFIX.'events', [
                'type' => 'failed_login',
                'severity' => self::SEVERITY_INFO,
                'title' => 'Login fallido',
                'detail' => $account.' en '.$channel.' desde '.$ip.'.',
                'ip' => $ip,
                'account' => $account,
                'at' => time(),
            ], 150, self::DAY);

            /** La cuenta atacada se protege aunque el intento venga de una IP de confianza. */
            $accountFailures = $store->increment(self::PREFIX.'fl:acct:'.$accountKey, $thresholds['account_lock_window']);
            $accountIps = $store->addToSet(self::PREFIX.'fl:acct-ips:'.$accountKey, $ip, $thresholds['distributed_window']);

            if ($accountFailures >= $thresholds['account_lock_failures'] && self::lockOf($store, $account) === null) {
                self::lockAccount($store, $account, $accountFailures, $accountIps);
            }

            if ($accountIps >= $thresholds['distributed_ips_per_account']) {
                self::emit($store, 'distributed:'.$accountKey, $thresholds['distributed_window'], [
                    'type' => 'distributed_attack',
                    'severity' => self::SEVERITY_CRITICAL,
                    'title' => 'Ataque distribuido a una cuenta',
                    'detail' => $account.' recibe intentos fallidos desde '.$accountIps.' IPs distintas.',
                    'account' => $account,
                    'ip' => $ip,
                ]);
            }

            if ($trusted) {
                return;
            }

            $store->scoreMember(self::PREFIX.'offenders', $ip, 3, self::DAY);
            $ipFailures = $store->increment(self::PREFIX.'fl:ip:'.$ip, $thresholds['failed_logins_per_ip_window']);
            $ipAccounts = $store->addToSet(self::PREFIX.'fl:ip-accounts:'.$ip, $account, $thresholds['emails_per_ip_window']);

            self::flagIp($store, $request, $ip, 'login fallido', 0, ['last_account' => $account, 'failed_logins' => $ipFailures, 'accounts_tried' => $ipAccounts]);

            if ($ipFailures >= $thresholds['failed_logins_per_ip']) {
                self::flagIp($store, $request, $ip, 'fuerza bruta', 10);
                self::emit($store, 'bruteforce:'.$ip, $thresholds['failed_logins_per_ip_window'], [
                    'type' => 'brute_force',
                    'severity' => self::SEVERITY_CRITICAL,
                    'title' => 'Fuerza bruta',
                    'detail' => $ipFailures.' logins fallidos desde '.$ip.' (último intento: '.$account.').',
                    'ip' => $ip,
                    'account' => $account,
                ]);
            }

            if ($ipAccounts >= $thresholds['emails_per_ip']) {
                self::flagIp($store, $request, $ip, 'relleno de credenciales', 10);
                self::emit($store, 'stuffing:'.$ip, $thresholds['emails_per_ip_window'], [
                    'type' => 'credential_stuffing',
                    'severity' => self::SEVERITY_CRITICAL,
                    'title' => 'Relleno de credenciales',
                    'detail' => $ipAccounts.' cuentas distintas probadas desde '.$ip.'.',
                    'ip' => $ip,
                ]);
            }
        });
    }

    /**
     * Login correcto: la racha de fallos de esa cuenta vuelve a cero.
     */
    public static function recordSuccessfulLogin(string $identifier): void
    {
        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($identifier): void {
            $store->forget(self::PREFIX.'fl:acct:'.self::accountKey(self::normalizeAccount($identifier)));
        });
    }

    /**
     * Cuenta usada desde dos países en la misma hora: posible cuenta robada o compartida.
     */
    public static function recordAuthenticatedCountry(int $userId, string $userName, string $countryCode, string $ip): void
    {
        if ($countryCode === '') {
            return;
        }

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($userId, $userName, $countryCode, $ip): void {
            $countries = $store->addToSet(self::PREFIX.'user-countries:'.$userId, $countryCode, 3600);

            if ($countries >= 2) {
                self::emit($store, 'multicountry:'.$userId, 3600, [
                    'type' => 'multi_country',
                    'severity' => self::SEVERITY_WARNING,
                    'title' => 'Cuenta usada desde varios países',
                    'detail' => $userName.' (ID '.$userId.') se conectó desde '.$countries.' países en la última hora; ahora desde '.$ip.'.',
                    'ip' => $ip,
                    'user_id' => $userId,
                ]);
            }
        });
    }

    /**
     * Bloqueo temporal vigente de una cuenta, o null. Si el almacenamiento falla
     * se permite el login: la disponibilidad pesa más que un bloqueo temporal.
     *
     * @return array<string, mixed>|null
     */
    public static function accountLock(string $identifier): ?array
    {
        try {
            return self::lockOf(LivePresenceStore::repository(), self::normalizeAccount($identifier));
        } catch (Throwable) {
            return null;
        }
    }

    public static function lockMessage(array $lock): string
    {
        $minutes = max(1, (int) ceil(((int) ($lock['until'] ?? time()) - time()) / 60));

        return 'Esta cuenta está bloqueada temporalmente por varios intentos fallidos. Intente de nuevo en '.$minutes.' '.($minutes === 1 ? 'minuto' : 'minutos').' o contacte a soporte.';
    }

    public static function unlockAccount(string $identifier, string $actor): void
    {
        $account = self::normalizeAccount($identifier);
        $key = self::accountKey($account);

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($account, $key, $actor): void {
            $store->forget(self::PREFIX.'lock:'.$key);
            $store->forget(self::PREFIX.'fl:acct:'.$key);
            self::emit($store, 'unlock:'.$key.':'.time(), 5, [
                'type' => 'account_unlocked',
                'severity' => self::SEVERITY_INFO,
                'title' => 'Cuenta desbloqueada a mano',
                'detail' => $account.' desbloqueada por '.$actor.'.',
                'account' => $account,
            ]);
        });
    }

    /**
     * Cuentas con bloqueo temporal vigente.
     *
     * @return list<array<string, mixed>>
     */
    public static function activeLocks(): array
    {
        try {
            $store = LivePresenceStore::repository();
            $locks = [];

            foreach (array_keys($store->topMembers(self::PREFIX.'locks', 50)) as $account) {
                $lock = self::lockOf($store, $account);

                if ($lock !== null) {
                    $locks[] = $lock;
                }
            }

            return $locks;
        } catch (Throwable) {
            return [];
        }
    }

    public static function isTrusted(string $ip): bool
    {
        $trusted = (array) config('live-presence.security.trusted_ips', []);

        return $trusted !== [] && $ip !== '' && IpUtils::checkIp($ip, $trusted);
    }

    public static function minute(?int $timestamp = null): string
    {
        return date('YmdHi', $timestamp ?? time());
    }

    public static function metricKey(string $metric, string $minute): string
    {
        return self::PREFIX.'m:'.$metric.':'.$minute;
    }

    public static function prefix(): string
    {
        return self::PREFIX;
    }

    public static function normalizeAccount(string $identifier): string
    {
        return Str::limit(mb_strtolower(trim($identifier)), 150, '') ?: '(vacío)';
    }

    public static function isBotAgent(string $userAgent): bool
    {
        $userAgent = mb_strtolower(trim($userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach ((array) config('live-presence.security.bot_agents', []) as $needle) {
            if ($needle !== '' && str_contains($userAgent, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    public static function scannerPathHit(Request $request): ?string
    {
        $path = mb_strtolower('/'.ltrim($request->path(), '/'));

        foreach ((array) config('live-presence.security.scanner_paths', []) as $needle) {
            $needle = mb_strtolower((string) $needle);

            if ($needle !== '' && (str_contains($path, '/'.$needle) || str_ends_with($path, $needle))) {
                return $needle;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private static function thresholds(): array
    {
        return array_map('intval', (array) config('live-presence.security.thresholds', []));
    }

    private static function bump(LivePresenceRepository $store, string $metric, string $minute): void
    {
        $store->increment(self::metricKey($metric, $minute), 7200);
    }

    /**
     * El propio monitor de TV sondea cada pocos segundos: no es actividad a vigilar.
     */
    private static function isMonitorTraffic(Request $request): bool
    {
        if ($request->is('monitor/tv/*')) {
            return true;
        }

        $referer = (string) parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);

        return str_starts_with($referer, '/monitor/tv/') && ActivityContext::isLivewireUpdate($request);
    }

    /**
     * @param  array<string, scalar|null>  $extra
     */
    private static function flagIp(LivePresenceRepository $store, Request $request, string $ip, string $tag, float $score, array $extra = []): void
    {
        if ($score > 0) {
            $store->scoreMember(self::PREFIX.'offenders', $ip, $score, self::DAY);
        } else {
            $store->scoreMember(self::PREFIX.'offenders', $ip, 0.01, self::DAY);
        }

        $key = self::PREFIX.'ipmeta:'.$ip;
        $meta = $store->getValue($key) ?? [];
        $tags = array_values(array_unique([...((array) ($meta['tags'] ?? [])), $tag]));
        $location = ClientLocation::locate($request, $ip);

        $store->putValue($key, [
            ...$meta,
            ...$extra,
            'ip' => $ip,
            'tags' => array_slice($tags, 0, 6),
            'last_seen' => time(),
            'last_path' => Str::limit('/'.ltrim($request->path(), '/'), 80),
            'user_agent' => Str::limit((string) $request->userAgent(), 160, ''),
            'location' => trim(implode(', ', array_filter([$location['city'], $location['country']]))),
            'country_code' => $location['country_code'],
        ], self::DAY);
    }

    /**
     * Emite un evento una sola vez por ventana ($dedupeSeconds) para el mismo sujeto.
     *
     * @param  array<string, mixed>  $event
     */
    private static function emit(LivePresenceRepository $store, string $dedupeKey, int $dedupeSeconds, array $event): void
    {
        $flag = self::PREFIX.'flag:'.$dedupeKey;

        if ($store->getValue($flag) !== null) {
            return;
        }

        $store->putValue($flag, ['at' => time()], max(5, $dedupeSeconds));

        $event = [...$event, 'at' => time(), 'id' => Str::lower(Str::random(10))];
        $store->pushList(self::PREFIX.'events', $event, 150, self::DAY);

        if (($event['severity'] ?? null) === self::SEVERITY_CRITICAL) {
            $store->pushList(self::PREFIX.'critical', $event, 50, self::DAY);
            SecurityAlertNotifier::notify($event);
        }
    }

    private static function lockAccount(LivePresenceRepository $store, string $account, int $failures, int $ips): void
    {
        $minutes = max(1, self::thresholds()['account_lock_minutes'] ?? 15);
        $until = time() + ($minutes * 60);

        $store->putValue(self::PREFIX.'lock:'.self::accountKey($account), [
            'account' => $account,
            'until' => $until,
            'failures' => $failures,
            'ips' => $ips,
            'locked_at' => time(),
        ], $minutes * 60);
        $store->scoreMember(self::PREFIX.'locks', $account, 1, self::DAY);

        self::emit($store, 'lock:'.self::accountKey($account), $minutes * 60, [
            'type' => 'account_locked',
            'severity' => self::SEVERITY_CRITICAL,
            'title' => 'Cuenta bloqueada temporalmente',
            'detail' => $account.' tras '.$failures.' intentos fallidos desde '.$ips.' '.($ips === 1 ? 'IP' : 'IPs').'. Bloqueo por '.$minutes.' min.',
            'account' => $account,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function lockOf(LivePresenceRepository $store, string $account): ?array
    {
        $lock = $store->getValue(self::PREFIX.'lock:'.self::accountKey($account));

        return $lock !== null && (int) ($lock['until'] ?? 0) > time() ? $lock : null;
    }

    private static function accountKey(string $account): string
    {
        return substr(sha1($account), 0, 20);
    }
}
