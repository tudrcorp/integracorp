<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Salud en vivo del canal WhatsApp, de punta a punta:
 *
 * - WhatsApp: estado de la conexión del número en Evolution API (open / connecting / close).
 * - n8n: el motor del bot responde a /healthz.
 * - Bot: llegan mensajes y salen respuestas; ninguno queda sin procesar.
 * - Bandeja: el bot entrega los casos a IntegraCorp y la cola `inbox` no se acumula.
 *
 * Todo corre en el mismo servidor (127.0.0.1) con tiempos de espera cortos. Cada sonda es
 * independiente: si una falla, las demás se siguen midiendo.
 */
final class CrmChannelHealth
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const DOWN = 'down';

    public const UNKNOWN = 'unknown';

    public const CACHE_SECONDS = 10;

    public const CHECKS = [
        'whatsapp' => 'WhatsApp (Evolution)',
        'n8n' => 'Motor del bot (n8n)',
        'bot' => 'Bot Sol',
        'inbox' => 'Bandeja',
    ];

    /** Mensajes en cola `inbox` a partir de los cuales la bandeja se marca en ámbar o en rojo. */
    public const INBOX_WARN = 20;

    public const INBOX_DOWN = 100;

    /** Errores del agente en la última hora a partir de los cuales el bot se marca en ámbar. */
    public const BOT_ERRORS_WARN = 3;

    /**
     * Lo que ve la página: medido hace a lo sumo diez segundos.
     *
     * @return array{at: int, checks: array<string, array{key: string, label: string, status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}>}
     */
    public static function current(): array
    {
        $cached = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get('crm-channel:health'));

        if (is_array($cached) && isset($cached['checks'])) {
            return $cached;
        }

        $probe = self::probe();

        CrmInboxCache::attempt(static fn (Repository $store): bool => $store->put('crm-channel:health', $probe, self::CACHE_SECONDS));

        return $probe;
    }

    /**
     * Mide todo ahora, sin caché.
     *
     * @return array{at: int, checks: array<string, array{key: string, label: string, status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}>}
     */
    public static function probe(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $checks = [];

        foreach (['whatsapp' => fn (): array => self::whatsapp(), 'n8n' => fn (): array => self::n8n(), 'bot' => fn (): array => self::bot($now), 'inbox' => fn (): array => self::inbox()] as $key => $check) {
            try {
                $result = $check();
            } catch (Throwable $exception) {
                report($exception);
                $result = ['status' => self::UNKNOWN, 'detail' => 'No se pudo medir.', 'latency_ms' => null, 'meta' => []];
            }

            $checks[$key] = ['key' => $key, 'label' => self::CHECKS[$key], ...$result];
        }

        return ['at' => $now->getTimestamp(), 'checks' => $checks];
    }

    public static function worst(array $checks): string
    {
        $rank = [self::OK => 0, self::UNKNOWN => 1, self::WARN => 2, self::DOWN => 3];
        $worst = self::OK;

        foreach ($checks as $check) {
            if (($rank[$check['status']] ?? 0) > $rank[$worst]) {
                $worst = $check['status'];
            }
        }

        return $worst;
    }

    /**
     * @return array{status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}
     */
    private static function whatsapp(): array
    {
        $url = rtrim(trim((string) config('crm-inbox.evolution.url', '')), '/');
        $key = trim((string) config('crm-inbox.evolution.key', ''));
        $instance = trim((string) config('crm-inbox.evolution.instance', ''));

        if ($url === '' || $key === '' || $instance === '') {
            return ['status' => self::UNKNOWN, 'detail' => 'Sin configurar: falta CRM_EVOLUTION_API_KEY en el .env de IntegraCorp.', 'latency_ms' => null, 'meta' => []];
        }

        $started = microtime(true);

        try {
            $response = Http::timeout(4)->connectTimeout(2)->acceptJson()
                ->withHeaders(['apikey' => $key])
                ->get($url.'/instance/connectionState/'.rawurlencode($instance));
        } catch (Throwable) {
            return ['status' => self::DOWN, 'detail' => 'Evolution API no responde.', 'latency_ms' => null, 'meta' => ['instance' => $instance]];
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($response->status() === 401 || $response->status() === 403) {
            return ['status' => self::UNKNOWN, 'detail' => 'La clave de Evolution no coincide.', 'latency_ms' => $latency, 'meta' => ['instance' => $instance]];
        }

        if ($response->status() === 404) {
            return ['status' => self::DOWN, 'detail' => 'La instancia «'.$instance.'» no existe en Evolution.', 'latency_ms' => $latency, 'meta' => ['instance' => $instance]];
        }

        $state = mb_strtolower((string) ($response->json('instance.state') ?? $response->json('state') ?? ''));
        $number = self::number($url, $key, $instance);
        $meta = ['instance' => $instance, 'state' => $state, 'number' => $number];

        return match ($state) {
            'open' => ['status' => self::OK, 'detail' => 'Conectado'.($number !== null ? ' · +'.$number : '').'.', 'latency_ms' => $latency, 'meta' => $meta],
            'connecting' => ['status' => self::WARN, 'detail' => 'Reconectando con WhatsApp…', 'latency_ms' => $latency, 'meta' => $meta],
            '' => ['status' => self::DOWN, 'detail' => 'Evolution respondió sin estado ('.$response->status().').', 'latency_ms' => $latency, 'meta' => $meta],
            default => ['status' => self::DOWN, 'detail' => 'Número desconectado: hay que escanear el QR otra vez.', 'latency_ms' => $latency, 'meta' => $meta],
        };
    }

    /**
     * Número vinculado a la instancia. Cambia muy poco: diez minutos de caché.
     */
    private static function number(string $url, string $key, string $instance): ?string
    {
        $cacheKey = 'crm-channel:number:'.$instance;
        $cached = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get($cacheKey));

        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        try {
            $rows = Http::timeout(4)->connectTimeout(2)->acceptJson()
                ->withHeaders(['apikey' => $key])
                ->get($url.'/instance/fetchInstances', ['instanceName' => $instance])
                ->json();
        } catch (Throwable) {
            return null;
        }

        $row = is_array($rows) ? (array_is_list($rows) ? ($rows[0] ?? []) : $rows) : [];
        $owner = is_array($row) ? (string) ($row['ownerJid'] ?? $row['owner'] ?? ($row['instance']['owner'] ?? '')) : '';
        $digits = preg_replace('/\D+/', '', explode('@', $owner)[0]) ?? '';
        $number = strlen($digits) >= 8 ? $digits : '';

        CrmInboxCache::attempt(static fn (Repository $store): bool => $store->put($cacheKey, $number, 600));

        return $number !== '' ? $number : null;
    }

    /**
     * @return array{status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}
     */
    private static function n8n(): array
    {
        $url = CrmBotMetrics::n8nHealthUrl();

        if ($url === '') {
            return ['status' => self::UNKNOWN, 'detail' => 'Sin configurar: falta CRM_N8N_TAKEOVER_URL.', 'latency_ms' => null, 'meta' => []];
        }

        $started = microtime(true);

        try {
            $response = Http::timeout(3)->connectTimeout(2)->get($url);
        } catch (Throwable) {
            return ['status' => self::DOWN, 'detail' => 'n8n no responde: el bot no puede contestar.', 'latency_ms' => null, 'meta' => []];
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        return $response->successful()
            ? ['status' => $latency > 2000 ? self::WARN : self::OK, 'detail' => $latency > 2000 ? 'Responde, pero lento.' : 'En línea.', 'latency_ms' => $latency, 'meta' => []]
            : ['status' => self::DOWN, 'detail' => 'n8n respondió con error '.$response->status().'.', 'latency_ms' => $latency, 'meta' => []];
    }

    /**
     * @return array{status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}
     */
    private static function bot(Carbon $now): array
    {
        $live = CrmBotMetrics::live();
        $data = $live['data'];

        if ($data === null) {
            return ['status' => self::UNKNOWN, 'detail' => 'Sin datos del bot: '.$live['error'].'.', 'latency_ms' => $live['latency_ms'], 'meta' => []];
        }

        $l = $data['live'];
        $clock = static fn (?int $ts): string => $ts !== null ? Carbon::createFromTimestamp($ts)->timezone('America/Caracas')->format('d/m H:i') : '—';
        $meta = $l;
        $activity = 'Recibió '.$l['in_60m'].' y envió '.$l['out_60m'].' mensajes en la última hora. Último mensaje '.$clock($l['last_in_at']).' · última respuesta '.$clock($l['last_out_at']).'.';

        if ($l['stuck'] > 0) {
            return ['status' => self::DOWN, 'detail' => $l['stuck'].' mensaje(s) de clientes sin responder desde las '.$clock($l['stuck_since']).'. '.$activity, 'latency_ms' => $live['latency_ms'], 'meta' => $meta];
        }

        if ($l['errors_60m'] >= self::BOT_ERRORS_WARN) {
            return ['status' => self::WARN, 'detail' => $l['errors_60m'].' errores del asistente en la última hora. '.$activity, 'latency_ms' => $live['latency_ms'], 'meta' => $meta];
        }

        if ($l['crm_pending'] > 0) {
            return ['status' => self::WARN, 'detail' => $l['crm_pending'].' caso(s) que el bot aún no logra entregar a la bandeja. '.$activity, 'latency_ms' => $live['latency_ms'], 'meta' => $meta];
        }

        return ['status' => self::OK, 'detail' => $activity, 'latency_ms' => $live['latency_ms'], 'meta' => $meta];
    }

    /**
     * @return array{status: string, detail: string, latency_ms: ?int, meta: array<string, mixed>}
     */
    private static function inbox(): array
    {
        $delivery = CrmInboxHealth::status();
        $backlog = self::backlog();
        $meta = ['backlog' => $backlog, 'delivery_down_since' => $delivery['since']];

        if ($delivery['down']) {
            return ['status' => self::DOWN, 'detail' => 'Fallan los envíos de la bandeja a WhatsApp.', 'latency_ms' => null, 'meta' => $meta];
        }

        if ($backlog !== null && $backlog >= self::INBOX_DOWN) {
            return ['status' => self::DOWN, 'detail' => $backlog.' trabajos acumulados en la cola: revisa los workers de la bandeja.', 'latency_ms' => null, 'meta' => $meta];
        }

        if ($backlog !== null && $backlog >= self::INBOX_WARN) {
            return ['status' => self::WARN, 'detail' => $backlog.' trabajos en cola: la bandeja va con retraso.', 'latency_ms' => null, 'meta' => $meta];
        }

        return ['status' => self::OK, 'detail' => $backlog === null ? 'Envíos al día.' : 'Envíos al día · '.$backlog.' en cola.', 'latency_ms' => null, 'meta' => $meta];
    }

    /**
     * Trabajos pendientes en la cola `inbox` de Redis. Null si la cola no es Redis o no responde.
     */
    public static function backlog(): ?int
    {
        $connection = (string) config('crm-inbox.queue_connection', 'redis');

        if ($connection !== 'redis') {
            return null;
        }

        try {
            $redis = Redis::connection((string) config('queue.connections.redis.connection', 'default'));

            return (int) $redis->llen('queues:'.config('crm-inbox.queue', 'inbox'))
                + (int) $redis->zcard('queues:'.config('crm-inbox.queue', 'inbox').':delayed');
        } catch (Throwable) {
            return null;
        }
    }
}
