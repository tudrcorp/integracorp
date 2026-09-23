<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lo que dibuja el monitor en cada refresco.
 *
 * Conectados: una lectura al almacenamiento de presencia (milisegundos).
 * Salud del sistema: colas, fallidos, base de datos y Redis, cacheada 10 s
 * para que refrescar cada 3 s no multiplique consultas.
 */
final class LiveActivitySnapshot
{
    private const HEALTH_CACHE_KEY = 'live-presence:system-health';

    private const HEALTH_TTL = 10;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sessions(): array
    {
        /**
         * Se listan todas las sesiones que aún no vencen (5 min), no solo las de
         * los últimos 90 s: quien lleva un rato sin señal se muestra como
         * «inactivo» en vez de desaparecer y reaparecer de la tabla.
         */
        try {
            $rows = LivePresenceStore::repository()->online(max(60, (int) config('live-presence.session_ttl', 300)));
        } catch (Throwable) {
            return [];
        }

        $now = time();

        return array_map(fn (array $row): array => self::present($row, $now), $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array<string, mixed>
     */
    public static function kpis(array $sessions): array
    {
        $users = [];
        $panels = [];
        $pwa = 0;
        $rtts = [];
        $active = 0;

        $idle = 0;
        $withoutHeartbeat = 0;

        foreach ($sessions as $session) {
            /** Los filtros por panel cuentan todas las filas de la tabla, inactivas incluidas. */
            $panels[$session['panel']] = ($panels[$session['panel']] ?? 0) + 1;

            /** Los indicadores cuentan solo a quien está conectado ahora; los inactivos se informan aparte. */
            if ($session['idle']) {
                $idle++;

                continue;
            }

            if (! $session['has_heartbeat']) {
                $withoutHeartbeat++;
            }

            $users[$session['user_id']] = true;

            if ($session['is_pwa']) {
                $pwa++;
            }

            if ($session['rtt_ms'] !== null) {
                $rtts[] = $session['rtt_ms'];
            }

            if ($session['visible']) {
                $active++;
            }
        }

        arsort($panels);
        $performance = self::performance();

        return [
            'users' => count($users),
            'sessions' => count($sessions) - $idle,
            'idle_sessions' => $idle,
            'listed' => count($sessions),
            'without_heartbeat' => $withoutHeartbeat,
            'active_tabs' => $active,
            'pwa' => $pwa,
            'panels' => $panels,
            'avg_rtt' => $rtts === [] ? null : (int) round(array_sum($rtts) / count($rtts)),
            ...$performance,
        ];
    }

    /**
     * Tiempo de respuesta del servidor en los últimos 5 minutos.
     *
     * @return array{rpm: int, avg_ms: int|null, p95_ms: int|null, max_ms: int|null}
     */
    public static function performance(): array
    {
        try {
            $samples = LivePresenceStore::repository()->durationSamples();
        } catch (Throwable) {
            $samples = [];
        }

        $now = time();
        $recent = array_values(array_filter($samples, static fn (array $sample): bool => $sample[0] >= $now - 300));
        $lastMinute = array_filter($recent, static fn (array $sample): bool => $sample[0] >= $now - 60);
        $durations = array_map(static fn (array $sample): float => $sample[1], $recent);
        sort($durations);

        if ($durations === []) {
            return ['rpm' => count($lastMinute), 'avg_ms' => null, 'p95_ms' => null, 'max_ms' => null];
        }

        $p95Index = (int) max(0, ceil(count($durations) * 0.95) - 1);

        return [
            'rpm' => count($lastMinute),
            'avg_ms' => (int) round(array_sum($durations) / count($durations)),
            'p95_ms' => (int) round($durations[$p95Index]),
            'max_ms' => (int) round(end($durations)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function systemHealth(): array
    {
        try {
            return Cache::remember(self::HEALTH_CACHE_KEY, self::HEALTH_TTL, static fn (): array => self::measureSystemHealth());
        } catch (Throwable) {
            return self::measureSystemHealth();
        }
    }

    /**
     * Tras reintentar o eliminar fallidos, el monitor debe verlo en el siguiente refresco.
     */
    public static function forgetSystemHealth(): void
    {
        try {
            Cache::forget(self::HEALTH_CACHE_KEY);
        } catch (Throwable) {
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function measureSystemHealth(): array
    {
        $health = [
            'measured_at' => now()->format('H:i:s'),
            'store' => LivePresenceStore::usesRedis() ? 'Redis' : 'Caché ('.config('cache.default').')',
            'geoip' => ClientLocation::geoIpAvailable(),
            'php' => PHP_VERSION,
            'environment' => (string) config('app.env'),
            'queue_driver' => (string) config('queue.default'),
            'queues' => [],
            'queue_report' => null,
            'queue_pending' => null,
            'failed_jobs' => null,
            'db_ms' => null,
            'redis' => null,
            'load' => null,
            'disk_free_pct' => null,
        ];

        $queues = QueueHealth::measure();
        $health['queue_report'] = $queues;
        $health['queue_pending'] = $queues['pending_total'];
        $health['failed_jobs'] = $queues['failed']['total'];

        foreach ($queues['queues'] as $queue) {
            $health['queues'][$queue['name']] = $queue['pending'];
        }

        try {
            $started = microtime(true);
            DB::select('select 1');
            $health['db_ms'] = round((microtime(true) - $started) * 1000, 1);
        } catch (Throwable) {
        }

        $repository = LivePresenceStore::repository();

        if ($repository instanceof RedisLivePresenceRepository) {
            try {
                $health['redis'] = $repository->serverInfo();
            } catch (Throwable) {
            }
        }

        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $health['load'] = is_array($load) ? array_map(static fn (float $value): float => round($value, 2), $load) : null;
        }

        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (is_float($total) && is_float($free) && $total > 0) {
            $health['disk_free_pct'] = (int) round($free / $total * 100);
        }

        return $health;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function present(array $row, int $now): array
    {
        $lastSeen = (int) ($row['last_seen'] ?? 0);
        $firstSeen = (int) ($row['first_seen'] ?? $lastSeen);
        $panel = (string) ($row['panel'] ?? 'web');
        $rtt = self::intOrNull($row['rtt_ms'] ?? null);

        return [
            'session_key' => (string) ($row['session_key'] ?? ''),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_name' => (string) ($row['user_name'] ?? 'Usuario'),
            'user_email' => (string) ($row['user_email'] ?? ''),
            'initials' => self::initials((string) ($row['user_name'] ?? '')),
            'panel' => $panel,
            'panel_label' => (string) ($row['panel_label'] ?? ActivityContext::panelLabel($panel)),
            'page_label' => (string) ($row['page_label'] ?? ''),
            'page_path' => (string) ($row['page_path'] ?? ''),
            'page_title' => (string) ($row['page_title'] ?? ''),
            'last_action' => (string) ($row['activity'] ?? ''),
            'last_action_ago' => isset($row['activity_at']) ? self::ago($now - (int) $row['activity_at']) : null,
            'ip' => (string) ($row['ip'] ?? ''),
            'location' => self::location($row),
            'country_code' => (string) ($row['country_code'] ?? ''),
            'flag' => self::flag((string) ($row['country_code'] ?? '')),
            'browser' => trim((string) ($row['browser'] ?? '').' '.(string) ($row['browser_version'] ?? '')),
            'os' => trim((string) ($row['os'] ?? '').' '.(string) ($row['os_version'] ?? '')),
            'device' => (string) ($row['device'] ?? 'desktop'),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'is_pwa' => $panel === ActivityContext::PWA,
            'pwa_installed' => ($row['pwa_installed'] ?? '0') === '1',
            'visible' => ($row['visible'] ?? '1') === '1',
            'rtt_ms' => $rtt,
            'rtt_level' => self::rttLevel($rtt),
            'conn_type' => (string) ($row['conn_type'] ?? ''),
            'downlink' => $row['downlink'] ?? null,
            'server_ms' => self::intOrNull($row['server_ms'] ?? null),
            'server_level' => self::serverLevel(self::intOrNull($row['server_ms'] ?? null)),
            'memory_mb' => $row['memory_mb'] ?? null,
            'queries' => self::intOrNull($row['queries'] ?? null),
            'status_code' => self::intOrNull($row['status_code'] ?? null),
            'load_ms' => self::intOrNull($row['load_ms'] ?? null),
            'ttfb_ms' => self::intOrNull($row['ttfb_ms'] ?? null),
            'screen' => (string) ($row['screen'] ?? ''),
            'device_memory' => $row['device_memory'] ?? null,
            'cores' => self::intOrNull($row['cores'] ?? null),
            'lang' => (string) ($row['lang'] ?? ''),
            'timezone' => (string) ($row['timezone'] ?? ''),
            'requests' => (int) ($row['requests'] ?? 0),
            'last_seen' => $lastSeen,
            'last_seen_ago' => self::ago($now - $lastSeen),
            'idle_seconds' => max(0, $now - $lastSeen),
            'idle' => ($now - $lastSeen) > max(30, (int) config('live-presence.online_window', 90)),
            /** Latido reciente: con la pestaña oculta late cada 60 s, así que se da margen de 2,5 veces. */
            'has_heartbeat' => isset($row['last_ping_at'])
                && ($now - (int) $row['last_ping_at']) <= (int) (max(15, (int) config('live-presence.hidden_heartbeat_seconds', 60)) * 2.5),
            'session_duration' => self::duration(max(0, $lastSeen - $firstSeen)),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function location(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['city'] ?? '')),
            trim((string) ($row['region'] ?? '')),
            trim((string) ($row['country'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return $parts === [] ? 'Ubicación desconocida' : implode(', ', array_unique($parts));
    }

    private static function flag(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($countryCode[0]) - 65).mb_chr(0x1F1E6 + ord($countryCode[1]) - 65);
    }

    private static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    private static function rttLevel(?int $rtt): string
    {
        return match (true) {
            $rtt === null => 'unknown',
            $rtt < 200 => 'good',
            $rtt < 600 => 'fair',
            default => 'poor',
        };
    }

    private static function serverLevel(?int $ms): string
    {
        return match (true) {
            $ms === null => 'unknown',
            $ms < 400 => 'good',
            $ms < 1200 => 'fair',
            default => 'poor',
        };
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    public static function ago(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return match (true) {
            $seconds < 5 => 'ahora',
            $seconds < 60 => 'hace '.$seconds.' s',
            $seconds < 3600 => 'hace '.intdiv($seconds, 60).' min',
            $seconds < 172800 => 'hace '.intdiv($seconds, 3600).' h',
            default => 'hace '.intdiv($seconds, 86400).' días',
        };
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        return intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min';
    }
}
