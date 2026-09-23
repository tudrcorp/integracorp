<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Models\FailedJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vigilante de colas y errores: lo ejecuta el scheduler cada minuto
 * (`live-presence:watch`), fuera de la cola, así que avisa aunque los workers
 * estén caídos, que es justo cuando más importa.
 *
 * Revisa: workers sin latido, colas sin nadie que las escuche, colas atascadas,
 * trabajos colgados, ráfagas de fallidos y errores nuevos o que regresan.
 * Cada problema avisa una vez y calla durante la pausa configurada.
 */
final class SystemHealthWatcher
{
    private const PREFIX = 'lp:sys:';

    /**
     * @return array{detected: int, sent: int, silenced: int}
     */
    public static function run(): array
    {
        if (! config('live-presence.enabled', true)) {
            return ['detected' => 0, 'sent' => 0, 'silenced' => 0];
        }

        $events = [...self::queueProblems(), ...self::failedSpike(), ...self::errorProblems()];
        $sent = 0;
        $silenced = 0;
        $maxPerRun = max(1, (int) config('live-presence.max_alerts_per_run', 5));
        $overflow = [];

        foreach ($events as $event) {
            if (self::isCoolingDown($event)) {
                $silenced++;

                continue;
            }

            if ($sent >= $maxPerRun) {
                $overflow[] = $event;

                continue;
            }

            self::send($event);
            $sent++;
        }

        if ($overflow !== []) {
            self::send([
                'type' => 'many_problems',
                'subject' => 'overflow',
                'severity' => SecurityMonitor::SEVERITY_WARNING,
                'title' => 'Hay '.count($overflow).' problemas más',
                'detail' => implode(' · ', array_map(static fn (array $event): string => (string) $event['title'], array_slice($overflow, 0, 6))),
                'action' => 'Ábralos en Negocios → Colas y errores.',
            ]);
            $sent++;
        }

        return ['detected' => count($events), 'sent' => $sent, 'silenced' => $silenced];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function queueProblems(?array $report = null): array
    {
        try {
            $report ??= QueueHealth::measure();
        } catch (Throwable) {
            return [];
        }

        $events = [];
        $workers = $report['workers'] ?? ['known' => false, 'alive' => []];
        $waiting = array_filter($report['queues'], static fn (array $queue): bool => (int) $queue['pending'] > 0
            && (int) ($queue['oldest_seconds'] ?? 0) >= (int) config('live-presence.queues.unattended_after_seconds', 60));

        if ($workers['known'] && $workers['alive'] === [] && $waiting !== []) {
            $events[] = [
                'type' => 'queue_no_workers',
                'subject' => 'all',
                'severity' => SecurityMonitor::SEVERITY_CRITICAL,
                'title' => 'Ningún worker está procesando las colas',
                'detail' => $report['pending_total'].' trabajos esperan en '.count($waiting).' '.(count($waiting) === 1 ? 'cola' : 'colas').' y ningún worker dio señal de vida en el último minuto.',
                'action' => 'Arranque el worker en el servidor: '.$report['worker_command'],
            ];

            return $events;
        }

        foreach ($report['queues'] as $queue) {
            $event = match ($queue['status']) {
                'unattended' => [
                    'type' => 'queue_unattended',
                    'severity' => SecurityMonitor::SEVERITY_CRITICAL,
                    'title' => 'Nadie atiende la cola «'.$queue['name'].'»',
                    'action' => 'Reinicie el worker con todas las colas: '.$report['worker_command'],
                ],
                'stuck' => [
                    'type' => 'queue_stuck',
                    'severity' => SecurityMonitor::SEVERITY_CRITICAL,
                    'title' => 'Cola «'.$queue['name'].'» atascada',
                    'action' => 'Revise que el worker esté corriendo y no esté saturado.',
                ],
                'zombie' => [
                    'type' => 'queue_zombies',
                    'severity' => SecurityMonitor::SEVERITY_WARNING,
                    'title' => 'Trabajos colgados en la cola «'.$queue['name'].'»',
                    'action' => 'Revise si el worker se reinició a mitad y el timeout de esos trabajos.',
                ],
                default => null,
            };

            if ($event !== null) {
                $events[] = [...$event, 'subject' => $queue['name'], 'detail' => $queue['advice']];
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function failedSpike(): array
    {
        $threshold = max(1, (int) config('live-presence.queues.failed_spike_per_10_min', 10));

        try {
            $recent = FailedJob::query()->where('failed_at', '>=', now()->subMinutes(10))->count();
        } catch (Throwable) {
            return [];
        }

        if ($recent < $threshold) {
            return [];
        }

        $group = FailedJobCatalog::groups(1)[0] ?? null;

        return [[
            'type' => 'failed_spike',
            'subject' => 'global',
            'severity' => SecurityMonitor::SEVERITY_CRITICAL,
            'title' => $recent.' trabajos fallaron en 10 minutos',
            'detail' => $group !== null
                ? 'La causa principal: '.$group['job'].' — '.$group['diagnosis']['title'].' ('.$group['count'].' en 24 h).'
                : 'Revise la causa en el detalle.',
            'action' => $group['diagnosis']['advice'] ?? 'Ábralos en Negocios → Colas y errores.',
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function errorProblems(): array
    {
        return array_map(static fn (array $alert): array => [
            ...$alert,
            'subject' => (string) ($alert['subject'] ?? ''),
            'action' => 'Abra el error en Negocios → Colas y errores → Errores del sistema: tiene la línea exacta y el botón «Copiar diagnóstico».',
        ], ErrorTracker::pullPendingAlerts());
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function isCoolingDown(array $event): bool
    {
        try {
            $store = LivePresenceStore::repository();
            $key = self::PREFIX.'alert:'.$event['type'].':'.sha1((string) ($event['subject'] ?? ''));

            if ($store->getValue($key) !== null) {
                return true;
            }

            $store->putValue($key, ['at' => time()], max(1, (int) config('live-presence.security.alert_cooldown_minutes', 30)) * 60);

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function send(array $event): void
    {
        try {
            NotifyLiveSecurityAlertJob::dispatchSync(
                [...$event, 'channel' => 'system', 'at' => (int) ($event['at'] ?? time())],
                SystemNotificationKey::LiveSystemAlert->value,
                true,
            );
        } catch (Throwable $exception) {
            try {
                Log::error('LivePresence: no se pudo enviar el aviso de colas y errores.', ['type' => $event['type'] ?? null, 'error' => $exception->getMessage()]);
            } catch (Throwable) {
            }
        }
    }
}
