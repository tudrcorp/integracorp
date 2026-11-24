<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Jobs\RefreshUltraMsgConnectionStatusJob;
use App\Models\WhatsAppInboundMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Conexión de nuestro sistema con la instancia de WhatsApp (UltraMsg), en los dos sentidos:
 *
 * - **Envío:** estado de la instancia (`/instance/status`): conectada, sin sesión (hay que
 *   escanear el QR), arrancando o sin respuesta.
 * - **Recepción:** cuándo llegó el último aviso del webhook.
 *
 * La pantalla nunca espera a UltraMsg: muestra el último resultado guardado y, si tiene más
 * de {@see self::FRESH_SECONDS} segundos, pide uno nuevo en cola. «Verificar ahora» consulta
 * en el momento (con un tiempo máximo corto).
 */
final class UltraMsgConnectionStatus
{
    public const STATE_CONNECTED = 'CONECTADA';

    public const STATE_DISCONNECTED = 'DESCONECTADA';

    public const STATE_UNREACHABLE = 'SIN_RESPUESTA';

    public const STATE_NOT_CONFIGURED = 'SIN_CONFIGURAR';

    public const STATE_UNKNOWN = 'SIN_VERIFICAR';

    public const FRESH_SECONDS = 60;

    /** Más viejo que esto, el dato se marca como desactualizado. */
    public const STALE_SECONDS = 600;

    /** Sin avisos del webhook en este tiempo, la recepción se marca en amarillo. */
    public const QUIET_WEBHOOK_HOURS = 6;

    public const CACHE_KEY = 'ultramsg:connection-status';

    public const WEBHOOK_CACHE_KEY = 'ultramsg:webhook:last-received-at';

    /**
     * Último estado conocido de la instancia, sin llamar a UltraMsg.
     *
     * @return array{state: string, label: string, detail: string, checked_at: string|null}
     */
    public static function current(): array
    {
        if (! UltraMsgClient::configured()) {
            return self::result(self::STATE_NOT_CONFIGURED, 'Falta el token o la instancia de UltraMsg en este servidor (config/parameters.php). Desde aquí no sale ningún WhatsApp.', now());
        }

        $cached = self::cached();

        if ($cached === null || self::ageInSeconds($cached) > self::FRESH_SECONDS) {
            self::queueRefresh();
        }

        return $cached ?? self::result(self::STATE_UNKNOWN, 'Todavía no se ha verificado. Pulse «Verificar ahora».', null);
    }

    /**
     * Consulta a UltraMsg en el momento y guarda el resultado.
     *
     * @return array{state: string, label: string, detail: string, checked_at: string|null}
     */
    public static function refresh(): array
    {
        if (! UltraMsgClient::configured()) {
            return self::current();
        }

        try {
            $response = UltraMsgClient::get('instance/status');
            $result = self::interpret($response['response'], $response['http_code'], $response['curl_error']);
        } catch (Throwable $exception) {
            $result = self::result(self::STATE_UNREACHABLE, 'No se pudo consultar a UltraMsg: '.mb_substr($exception->getMessage(), 0, 200), now());
        }

        try {
            Cache::forever(self::CACHE_KEY, $result);
        } catch (Throwable) {
            /** Sin cache solo se pierde el último resultado guardado. */
        }

        return $result;
    }

    /**
     * Respuesta de `/instance/status`:
     * `{"status":{"accountStatus":{"status":"authenticated","substatus":"connected"}}}`.
     *
     * @return array{state: string, label: string, detail: string, checked_at: string|null}
     */
    public static function interpret(mixed $response, int $httpCode, string $curlError = ''): array
    {
        if ($curlError !== '') {
            return self::result(self::STATE_UNREACHABLE, 'UltraMsg no responde: '.mb_substr($curlError, 0, 200), now());
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;

        if (! is_array($decoded)) {
            return self::result(self::STATE_UNREACHABLE, 'UltraMsg respondió '.$httpCode.' sin un estado legible.', now());
        }

        if (isset($decoded['error'])) {
            $error = is_scalar($decoded['error']) ? (string) $decoded['error'] : (string) json_encode($decoded['error']);

            return self::result(self::STATE_UNREACHABLE, 'UltraMsg rechazó la consulta: '.mb_substr($error, 0, 200).' (revise el token y la instancia).', now());
        }

        $account = $decoded['status']['accountStatus'] ?? $decoded['accountStatus'] ?? null;
        $status = is_array($account) ? strtolower(trim((string) ($account['status'] ?? ''))) : '';
        $substatus = is_array($account) ? strtolower(trim((string) ($account['substatus'] ?? ''))) : '';

        if ($status === 'authenticated' && in_array($substatus, ['', 'connected'], true)) {
            return self::result(self::STATE_CONNECTED, 'La instancia está conectada a WhatsApp y puede enviar mensajes.', now());
        }

        $detail = match (true) {
            $status === 'authenticated' => 'La sesión de WhatsApp está abierta pero el teléfono de la instancia no está conectado ('.$substatus.'). Revise que tenga internet y batería.',
            in_array($status, ['qr', 'got qr code'], true) => 'La instancia perdió la sesión de WhatsApp: hay que escanear el código QR otra vez desde el panel de UltraMsg.',
            in_array($status, ['loading', 'initialize', 'retrying'], true) => 'La instancia está arrancando ('.$status.'). Si sigue así en unos minutos, revísela en el panel de UltraMsg.',
            in_array($status, ['standby', 'disconnected'], true) => 'La instancia está desconectada ('.$status.'). Reiníciela desde el panel de UltraMsg.',
            default => 'Estado de la instancia: '.($status !== '' ? $status : 'desconocido').($substatus !== '' ? ' / '.$substatus : '').'.',
        };

        return self::result(self::STATE_DISCONNECTED, $detail, now());
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_CONNECTED => 'Conectada',
            self::STATE_DISCONNECTED => 'Desconectada',
            self::STATE_UNREACHABLE => 'UltraMsg no responde',
            self::STATE_NOT_CONFIGURED => 'Sin configurar',
            default => 'Sin verificar',
        };
    }

    /**
     * Lo llama el webhook en cada aviso válido (con la clave correcta).
     */
    public static function markWebhookReceived(): void
    {
        try {
            Cache::forever(self::WEBHOOK_CACHE_KEY, now()->toIso8601String());
        } catch (Throwable) {
            /** El aviso se procesa igual; solo se pierde la hora del último. */
        }
    }

    public static function lastWebhookAt(): ?Carbon
    {
        try {
            $cached = Cache::get(self::WEBHOOK_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return Carbon::parse($cached);
            }
        } catch (Throwable) {
            /** Se cae a la tabla de mensajes recibidos. */
        }

        if (! Schema::hasTable('whatsapp_inbound_messages')) {
            return null;
        }

        $last = WhatsAppInboundMessage::query()->latest('id')->value('received_at');

        return $last !== null ? Carbon::parse($last) : null;
    }

    /**
     * @return array{state: string, label: string, detail: string}
     */
    public static function webhook(): array
    {
        if (blank(config('services.ultramsg_webhook.secret'))) {
            return [
                'state' => 'warning',
                'label' => 'Sin configurar',
                'detail' => 'Falta ULTRAMSG_WEBHOOK_SECRET en este servidor: no llegan las respuestas SI/NO ni las confirmaciones de entrega.',
            ];
        }

        $last = self::lastWebhookAt();

        if ($last === null) {
            return [
                'state' => 'gray',
                'label' => 'Sin avisos todavía',
                'detail' => 'Todavía no ha llegado ningún aviso de UltraMsg. Envíe una prueba y respóndala.',
            ];
        }

        $quiet = $last->lt(now()->subHours(self::QUIET_WEBHOOK_HOURS));

        return [
            'state' => $quiet ? 'warning' : 'success',
            'label' => $quiet ? 'Sin avisos recientes' : 'Recibiendo',
            'detail' => 'Último aviso de UltraMsg '.$last->diffForHumans().' ('.$last->format('d/m/Y h:i A').').'
                .($quiet ? ' Si se han enviado mensajes desde entonces, revise el webhook en el panel de UltraMsg.' : ''),
        ];
    }

    /**
     * @param  array{state: string, label: string, detail: string, checked_at: string|null}  $status
     */
    public static function ageInSeconds(array $status): int
    {
        return $status['checked_at'] !== null ? max(0, (int) Carbon::parse($status['checked_at'])->diffInSeconds(now())) : PHP_INT_MAX;
    }

    /**
     * @return array{state: string, label: string, detail: string, checked_at: string|null}|null
     */
    private static function cached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return null;
        }

        return is_array($cached) && isset($cached['state'], $cached['detail']) && array_key_exists('checked_at', $cached)
            ? [
                'state' => (string) $cached['state'],
                'label' => self::stateLabel((string) $cached['state']),
                'detail' => (string) $cached['detail'],
                'checked_at' => is_string($cached['checked_at']) ? $cached['checked_at'] : null,
            ]
            : null;
    }

    private static function queueRefresh(): void
    {
        try {
            RefreshUltraMsgConnectionStatusJob::dispatch()->onQueue(ServiceOrderPatientNotifier::queueName());
        } catch (Throwable) {
            /** Sin cola, el dato se actualiza con «Verificar ahora». */
        }
    }

    /**
     * @return array{state: string, label: string, detail: string, checked_at: string|null}
     */
    private static function result(string $state, string $detail, ?Carbon $checkedAt): array
    {
        return [
            'state' => $state,
            'label' => self::stateLabel($state),
            'detail' => $detail,
            'checked_at' => $checkedAt?->toIso8601String(),
        ];
    }
}
