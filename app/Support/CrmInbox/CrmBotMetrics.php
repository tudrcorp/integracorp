<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Métricas del bot Sol para Supervisión WhatsApp. Viven en el Postgres del bot: se piden al webhook
 * de solo lectura `handoff/metrics` de n8n (clave X-Handoff-Key) y se guardan en caché.
 *
 * Nunca rompe la página: si n8n no responde, devuelve null y la vista muestra «sin datos del bot».
 */
final class CrmBotMetrics
{
    /** Periodo (Hoy, 7 o 30 días): un minuto de caché. */
    public const PERIOD_TTL = 60;

    /** Vida del bot (última hora): diez segundos de caché. */
    public const LIVE_TTL = 10;

    public const TIMEOUT = 5;

    public const FAILURE_TTL = 30;

    public static function url(): string
    {
        $configured = trim((string) config('crm-inbox.metrics_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $takeover = trim((string) config('crm-inbox.takeover_url', ''));

        return str_ends_with($takeover, '/handoff/takeover')
            ? substr($takeover, 0, -strlen('takeover')).'metrics'
            : '';
    }

    public static function n8nHealthUrl(): string
    {
        $configured = trim((string) config('crm-inbox.n8n_health_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $parts = parse_url(trim((string) config('crm-inbox.takeover_url', '')));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/healthz';
    }

    public static function configured(): bool
    {
        return self::url() !== '' && trim((string) config('crm-inbox.takeover_key', '')) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forRange(string $range, ?Carbon $now = null): ?array
    {
        $now ??= Carbon::now();
        $range = isset(CrmInboxDashboard::RANGES[$range]) ? $range : 'hoy';

        return self::cached('crm-bot:metrics:'.$range, self::PERIOD_TTL, fn (): ?array => self::fetch(CrmInboxDashboard::rangeStart($range, $now)));
    }

    /**
     * Vida del bot: lo de los últimos minutos (último mensaje, mensajes sin responder, errores).
     *
     * @return array{data: array<string, mixed>|null, latency_ms: ?int, error: ?string}
     */
    public static function live(): array
    {
        $cached = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get('crm-bot:live'));

        if (is_array($cached) && array_key_exists('data', $cached)) {
            return $cached;
        }

        $started = microtime(true);
        $error = null;
        $data = self::fetch(Carbon::now()->subHour(), $error);
        $result = [
            'data' => $data,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $data === null ? ($error ?? 'sin respuesta') : null,
        ];

        CrmInboxCache::attempt(static fn (Repository $store): bool => $store->put('crm-bot:live', $result, self::LIVE_TTL));

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetch(Carbon $from, ?string &$error = null): ?array
    {
        if (! self::configured()) {
            $error = 'sin configurar';

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(2)
                ->acceptJson()
                ->withHeaders(['X-Handoff-Key' => (string) config('crm-inbox.takeover_key')])
                ->post(self::url(), [
                    'desde' => $from->copy()->utc()->toIso8601ZuluString(),
                    'tz' => 'America/Caracas',
                ]);
        } catch (Throwable $exception) {
            $error = 'n8n no responde';
            Log::notice('CRM supervisión: no se pudieron leer las métricas del bot', ['error' => $exception->getMessage()]);

            return null;
        }

        if ($response->status() === 404) {
            $error = 'el webhook de métricas no está publicado en n8n';

            return null;
        }

        if ($response->status() === 401) {
            $error = 'la clave del bot no coincide';

            return null;
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['ok'] ?? false) !== true || ! is_array($json['live'] ?? null)) {
            $error = 'respuesta inválida del bot';

            return null;
        }

        return self::normalize($json);
    }

    /**
     * Deja solo lo que la vista usa, con tipos seguros: un cambio en el bot no rompe la página.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public static function normalize(array $json): array
    {
        $int = static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0;
        $time = static function (mixed $value): ?int {
            if (! is_string($value) || $value === '') {
                return null;
            }

            try {
                return Carbon::parse($value)->getTimestamp();
            } catch (Throwable) {
                return null;
            }
        };
        $text = static fn (mixed $value, int $max = 200): string => mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $max);

        $live = is_array($json['live'] ?? null) ? $json['live'] : [];
        $period = is_array($json['periodo'] ?? null) ? $json['periodo'] : [];
        $counts = static fn (mixed $map): array => is_array($map)
            ? array_map($int, array_filter($map, static fn (mixed $value, mixed $key): bool => is_string($key) && is_numeric($value), ARRAY_FILTER_USE_BOTH))
            : [];

        $periodKeys = ['clientes', 'clientes_nuevos', 'mensajes_in', 'mensajes_out', 'audios_in', 'cotizaciones', 'clientes_cotizados',
            'propuestas_pdf', 'derivaciones', 'clientes_derivados', 'resueltos_sin_humano', 'derivaciones_repetidas', 'errores',
            'errores_escalados', 'seguimientos_enviados', 'seguimientos_respondidos', 'citas', 'links_pago', 'ofrecio_humano', 'finalizadas'];

        return [
            'live' => [
                'last_in_at' => $time($live['last_in_at'] ?? null),
                'last_out_at' => $time($live['last_out_at'] ?? null),
                'in_5m' => $int($live['in_5m'] ?? 0),
                'out_5m' => $int($live['out_5m'] ?? 0),
                'in_60m' => $int($live['in_60m'] ?? 0),
                'out_60m' => $int($live['out_60m'] ?? 0),
                'stuck' => $int($live['stuck'] ?? 0),
                'stuck_since' => $time($live['stuck_since'] ?? null),
                'open_handoffs' => $int($live['open_handoffs'] ?? 0),
                'takeover_now' => $int($live['takeover_now'] ?? 0),
                'crm_pending' => $int($live['crm_pending'] ?? 0),
                'errors_60m' => $int($live['errors_60m'] ?? 0),
            ],
            'period' => array_combine($periodKeys, array_map(static fn (string $key): int => $int($period[$key] ?? 0), $periodKeys)),
            'areas' => $counts($json['areas'] ?? []),
            'stages' => $counts($json['etapas'] ?? []),
            'events' => $counts($json['eventos'] ?? []),
            'reasons' => array_values(array_map(static fn (mixed $row): array => [
                'motivo' => $text(is_array($row) ? ($row['motivo'] ?? '') : ''),
                'area' => $text(is_array($row) ? ($row['area'] ?? '') : '', 40),
                'at' => $time(is_array($row) ? ($row['at'] ?? null) : null),
            ], array_slice(is_array($json['motivos_recientes'] ?? null) ? $json['motivos_recientes'] : [], 0, 12))),
            'errors' => array_values(array_map(static fn (mixed $row): array => [
                'error' => $text(is_array($row) ? ($row['error'] ?? '') : ''),
                'escalated' => is_array($row) && ($row['event'] ?? '') === 'agent_error_escalated',
                'at' => $time(is_array($row) ? ($row['at'] ?? null) : null),
            ], array_slice(is_array($json['errores_recientes'] ?? null) ? $json['errores_recientes'] : [], 0, 8))),
            'days' => array_values(array_map(static fn (mixed $row): array => [
                'day' => $text(is_array($row) ? ($row['dia'] ?? '') : '', 10),
                'clients' => $int(is_array($row) ? ($row['clientes'] ?? 0) : 0),
                'messages' => $int(is_array($row) ? ($row['mensajes_in'] ?? 0) : 0),
                'handoffs' => $int(is_array($row) ? ($row['derivaciones'] ?? 0) : 0),
            ], array_slice(is_array($json['por_dia'] ?? null) ? $json['por_dia'] : [], 0, 93))),
            'hours' => self::hours(is_array($json['por_hora'] ?? null) ? $json['por_hora'] : []),
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<int> 24 posiciones, de 00 a 23 h
     */
    private static function hours(array $rows): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            if (is_array($row) && is_numeric($row['hora'] ?? null) && (int) $row['hora'] >= 0 && (int) $row['hora'] <= 23) {
                $hours[(int) $row['hora']] = max(0, (int) ($row['mensajes_in'] ?? 0));
            }
        }

        return $hours;
    }

    /**
     * Una respuesta buena se guarda `$ttl` segundos; un fallo, 30 segundos, para que la página no
     * espere el tiempo máximo de n8n en cada refresco mientras el bot está caído.
     *
     * @param  callable(): (array<string, mixed>|null)  $fetch
     * @return array<string, mixed>|null
     */
    private static function cached(string $key, int $ttl, callable $fetch): ?array
    {
        $hit = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get($key));

        if (is_array($hit)) {
            return ($hit['__none'] ?? false) === true ? null : $hit;
        }

        $data = $fetch();

        CrmInboxCache::attempt(static fn (Repository $store): bool => $data !== null
            ? $store->put($key, $data, $ttl)
            : $store->put($key, ['__none' => true], self::FAILURE_TTL));

        return $data;
    }
}
