<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\FailedJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lectura de los trabajos fallidos para decidir rápido qué hacer con ellos.
 *
 * Agrupa por causa (trabajo + clase de error + mensaje sin IDs), le pone un
 * diagnóstico y una acción recomendada a cada grupo, y abre el detalle de un
 * fallido con la línea exacta de nuestro código donde se originó.
 *
 * Nunca se deserializa el payload: es un objeto PHP guardado por la cola y
 * deserializarlo desde una pantalla sería abrir la puerta a inyección de
 * objetos. Los modelos que llevaba se leen del texto con expresiones regulares.
 */
final class FailedJobCatalog
{
    /** Filas que se leen para agrupar: las más recientes. */
    private const GROUP_SAMPLE = 5000;

    /** Del error solo se lee el comienzo: el mensaje y dónde se lanzó. */
    private const EXCEPTION_HEAD = 1500;

    private const CACHE_KEY = 'live-presence:failed-groups';

    private const CACHE_TTL = 30;

    /** Trabajos que envían algo afuera: reintentarlos puede duplicar el envío. */
    private const OUTBOUND_PATTERN = '/whatsapp|mail|notif|send|email|sms|push|webhook|viveplus/i';

    /** Eventos internos del panel que el patrón confundiría con un envío externo. */
    private const NOT_OUTBOUND = ['DatabaseNotificationsSent', 'BroadcastEvent', 'BroadcastNotificationCreated'];

    /**
     * Causas de los fallidos, de la más repetida a la menos.
     *
     * @return list<array{fingerprint: string, job: string, job_class: string, queues: list<string>, exception: string, message: string, count: int, first_at: string|null, last_at: string|null, last_ago: string, last_uuid: string, days_since_last: int, sends_messages: bool, diagnosis: array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}}>
     */
    public static function groups(?int $days = null): array
    {
        $key = self::CACHE_KEY.':'.($days ?? 'all');

        try {
            return Cache::remember($key, self::CACHE_TTL, static fn (): array => self::buildGroups($days));
        } catch (Throwable) {
            return self::buildGroups($days);
        }
    }

    public static function forgetCache(): void
    {
        foreach (['all', 1, 7, 30] as $days) {
            try {
                Cache::forget(self::CACHE_KEY.':'.$days);
            } catch (Throwable) {
            }
        }

        LiveActivitySnapshot::forgetSystemHealth();
    }

    /**
     * @return array{fingerprint: string, job: string, job_class: string, queues: list<string>, exception: string, message: string, count: int, first_at: string|null, last_at: string|null, last_ago: string, last_uuid: string, days_since_last: int, sends_messages: bool, diagnosis: array<string, string>}|null
     */
    public static function group(string $fingerprint): ?array
    {
        foreach (self::groups() as $group) {
            if ($group['fingerprint'] === $fingerprint) {
                return $group;
            }
        }

        return null;
    }

    /**
     * UUIDs de todos los fallidos de un grupo, en toda la tabla (no solo la muestra).
     *
     * @return list<string>
     */
    public static function uuidsForGroup(string $fingerprint): array
    {
        $uuids = [];

        FailedJob::query()
            ->select('id', 'uuid')
            ->selectRaw('SUBSTR(payload, 1, 400) AS payload_head')
            ->selectRaw('SUBSTR(exception, 1, ?) AS exception_head', [self::EXCEPTION_HEAD])
            ->chunkById(500, function ($rows) use ($fingerprint, &$uuids): void {
                foreach ($rows as $row) {
                    if (self::fingerprintFor((string) $row->payload_head, (string) $row->exception_head) === $fingerprint) {
                        $uuids[] = (string) $row->uuid;
                    }
                }
            });

        return $uuids;
    }

    /**
     * Todo lo necesario para entender y corregir un fallido.
     *
     * @return array<string, mixed>|null
     */
    public static function detail(string $uuid): ?array
    {
        $row = FailedJob::query()->where('uuid', $uuid)->first();

        if ($row === null) {
            return null;
        }

        $payload = (string) $row->payload;
        $decoded = json_decode($payload, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $command = (string) ($decoded['data']['command'] ?? '');
        $exception = ExceptionFingerprint::fromReport((string) $row->exception);
        $jobClass = FailedJob::jobClassFromPayload($payload);
        $failedAt = $row->failed_at instanceof Carbon ? $row->failed_at : null;

        $detail = [
            'id' => (int) $row->id,
            'uuid' => (string) $row->uuid,
            'fingerprint' => self::fingerprintFor($payload, (string) $row->exception),
            'job' => class_basename($jobClass),
            'job_class' => $jobClass,
            'connection' => (string) $row->connection,
            'queue' => (string) $row->queue,
            'failed_at' => $failedAt?->format('d/m/Y H:i:s'),
            'failed_ago' => $failedAt !== null ? LiveActivitySnapshot::ago(max(0, time() - $failedAt->getTimestamp())) : '',
            'max_tries' => $decoded['maxTries'] ?? null,
            'timeout' => $decoded['timeout'] ?? null,
            'backoff' => $decoded['backoff'] ?? null,
            'encrypted' => $command !== '' && ! str_starts_with($command, 'O:'),
            'models' => self::modelsIn($command),
            'command_preview' => mb_strimwidth($command, 0, 3000, '…'),
            'exception' => $exception,
            'exception_text' => mb_strimwidth((string) $row->exception, 0, 20000, "\n…"),
            'diagnosis' => FailureDiagnosis::diagnose($exception['class'], $exception['message']),
            'sends_messages' => self::sendsMessages($jobClass),
        ];

        $detail['copy_text'] = self::copyText($detail);

        return $detail;
    }

    /**
     * Texto para pegar en el IDE, en un ticket o a un asistente: qué falló,
     * dónde y la traza de nuestro código.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function copyText(array $detail): string
    {
        $exception = $detail['exception'];
        $lines = [
            'Trabajo fallido: '.$detail['job_class'].' (cola '.$detail['queue'].', '.$detail['failed_at'].')',
            'Diagnóstico: '.$detail['diagnosis']['title'].' — '.$detail['diagnosis']['category_label'],
            'Error: '.$exception['class'].': '.$exception['message'],
            'Lanzado en: '.($exception['location'] ?: '—'),
            'Origen en nuestro código: '.($exception['origin'] ?: '—'),
        ];

        if ($detail['models'] !== []) {
            $lines[] = 'Datos: '.implode(', ', array_map(static fn (array $model): string => $model['label'], $detail['models']));
        }

        if ($exception['app_frames'] !== []) {
            $lines[] = 'Traza (solo nuestro código):';

            foreach (array_slice($exception['app_frames'], 0, 12) as $frame) {
                $lines[] = '  '.$frame['file'].($frame['line'] !== null ? ':'.$frame['line'] : '').'  '.$frame['call'];
            }
        }

        $lines[] = 'UUID: '.$detail['uuid'];

        return implode("\n", $lines);
    }

    public static function sendsMessages(string $jobClass): bool
    {
        $name = class_basename($jobClass);

        return ! in_array($name, self::NOT_OUTBOUND, true) && preg_match(self::OUTBOUND_PATTERN, $name) === 1;
    }

    /**
     * Huella de un fallido a partir del comienzo del payload y de la excepción.
     */
    public static function fingerprintFor(string $payloadHead, string $exceptionHead): string
    {
        $exception = ExceptionFingerprint::fromReport($exceptionHead);

        return ExceptionFingerprint::fingerprint(FailedJob::jobClassFromPayload($payloadHead), $exception['class'], $exception['message'], '');
    }

    /**
     * Modelos que llevaba el trabajo (ModelIdentifier del comando serializado).
     *
     * @return list<array{class: string, id: string, label: string}>
     */
    public static function modelsIn(string $command): array
    {
        preg_match_all('/s:5:"class";s:\d+:"([^"]+)";s:2:"id";(?:i:(\d+)|s:\d+:"([^"]*)")/', $command, $matches, PREG_SET_ORDER);

        $models = [];

        foreach ($matches as $match) {
            $id = ($match[2] ?? '') !== '' ? $match[2] : (string) ($match[3] ?? '');
            $key = $match[1].'#'.$id;
            $models[$key] = ['class' => $match[1], 'id' => $id, 'label' => class_basename($match[1]).' #'.$id];
        }

        return array_values(array_slice($models, 0, 20));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildGroups(?int $days): array
    {
        try {
            $rows = FailedJob::query()
                ->select('uuid', 'queue', 'failed_at')
                ->selectRaw('SUBSTR(payload, 1, 400) AS payload_head')
                ->selectRaw('SUBSTR(exception, 1, ?) AS exception_head', [self::EXCEPTION_HEAD])
                ->when($days !== null, fn ($query) => $query->where('failed_at', '>=', now()->subDays($days)))
                ->orderByDesc('failed_at')
                ->orderByDesc('id')
                ->limit(self::GROUP_SAMPLE)
                ->get();
        } catch (Throwable) {
            return [];
        }

        $staleAfter = max(1, (int) config('live-presence.queues.stale_group_days', 7));
        $groups = [];

        foreach ($rows as $row) {
            $payloadHead = (string) $row->payload_head;
            $exception = ExceptionFingerprint::fromReport((string) $row->exception_head);
            $jobClass = FailedJob::jobClassFromPayload($payloadHead);
            $fingerprint = ExceptionFingerprint::fingerprint($jobClass, $exception['class'], $exception['message'], '');
            $failedAt = $row->failed_at instanceof Carbon ? $row->failed_at : null;

            if (! isset($groups[$fingerprint])) {
                $groups[$fingerprint] = [
                    'fingerprint' => $fingerprint,
                    'job' => class_basename($jobClass),
                    'job_class' => $jobClass,
                    'queues' => [],
                    'exception' => $exception['short_class'],
                    'exception_class' => $exception['class'],
                    'message' => $exception['message'],
                    'location' => $exception['location'],
                    'count' => 0,
                    'first_at' => null,
                    'last_at' => $failedAt?->format('d/m/Y H:i'),
                    'last_timestamp' => $failedAt?->getTimestamp() ?? 0,
                    'last_uuid' => (string) $row->uuid,
                ];
            }

            $groups[$fingerprint]['count']++;
            $groups[$fingerprint]['queues'][(string) $row->queue] = true;
            $groups[$fingerprint]['first_at'] = $failedAt?->format('d/m/Y H:i');
        }

        $now = time();
        $result = [];

        foreach ($groups as $group) {
            $daysSinceLast = $group['last_timestamp'] > 0 ? intdiv(max(0, $now - $group['last_timestamp']), 86400) : 0;
            $diagnosis = FailureDiagnosis::forStaleGroup(
                FailureDiagnosis::diagnose($group['exception_class'], $group['message']),
                $daysSinceLast,
                $staleAfter,
                self::sendsMessages($group['job_class']),
            );

            $result[] = [
                ...$group,
                'queues' => array_keys($group['queues']),
                'last_ago' => $group['last_timestamp'] > 0 ? LiveActivitySnapshot::ago($now - $group['last_timestamp']) : '',
                'days_since_last' => $daysSinceLast,
                'sends_messages' => self::sendsMessages($group['job_class']),
                'diagnosis' => $diagnosis,
            ];
        }

        usort($result, static fn (array $a, array $b): int => [$b['count'], $b['last_timestamp']] <=> [$a['count'], $a['last_timestamp']]);

        return $result;
    }
}
