<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Models\FailedJob;
use App\Models\NotificationDelivery;
use App\Support\Telegram\TelegramHealth;
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

        $events = [...self::queueProblems(), ...self::failedSpike(), ...self::errorProblems(), ...self::telegramProblems()];
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
     * Telegram (notificaciones internas): el bot no responde, el webhook acumula
     * errores, o hay avisos que no se pudieron entregar. Estas alertas salen por
     * WhatsApp y correo, nunca por Telegram.
     *
     * @return list<array<string, mixed>>
     */
    public static function telegramProblems(?array $health = null): array
    {
        if (! (bool) config('telegram.enabled', true) || blank(config('telegram.bot_token'))) {
            return [];
        }

        $events = [];

        try {
            $health ??= TelegramHealth::check();
        } catch (Throwable) {
            return [];
        }

        if (! ($health['ok'] ?? false)) {
            $events[] = [
                'type' => 'telegram_down',
                'subject' => 'bot',
                'severity' => SecurityMonitor::SEVERITY_CRITICAL,
                'title' => 'Telegram no responde',
                'detail' => 'El bot no contestó: '.($health['error'] ?? 'sin detalle').'. Mientras tanto los avisos internos salen por WhatsApp y correo.',
                'action' => ($health['error_kind'] ?? null) === 'unauthorized'
                    ? 'El token del bot es inválido o fue revocado: genere uno nuevo en @BotFather y actualice TELEGRAM_BOT_TOKEN.'
                    : 'Revise la conexión del servidor con api.telegram.org y el estado en Negocios → CONFIGURACIÓN → Telegram → «Estado del bot».',
            ];
        } elseif (filled($health['webhook_last_error'] ?? null) && (int) ($health['webhook_last_error_at'] ?? 0) >= time() - 600) {
            $events[] = [
                'type' => 'telegram_webhook',
                'subject' => 'webhook',
                'severity' => SecurityMonitor::SEVERITY_WARNING,
                'title' => 'Telegram no puede entregar al webhook',
                'detail' => 'Último error informado por Telegram: '.$health['webhook_last_error'].'. Las vinculaciones y los grupos nuevos no se registran hasta que se resuelva.',
                'action' => 'Verifique que '.route('telegram.webhook').' responda y vuelva a registrar con php artisan telegram:set-webhook.',
            ];
        } elseif ((int) ($health['pending_update_count'] ?? 0) >= TelegramHealth::PENDING_UPDATES_THRESHOLD) {
            $events[] = [
                'type' => 'telegram_webhook',
                'subject' => 'pending',
                'severity' => SecurityMonitor::SEVERITY_WARNING,
                'title' => 'Telegram acumula mensajes sin procesar',
                'detail' => (int) $health['pending_update_count'].' mensajes esperan en Telegram: el webhook no está registrado o no responde.',
                'action' => 'Registre el webhook con php artisan telegram:set-webhook.',
            ];
        }

        try {
            $stuck = NotificationDelivery::query()
                ->where('status', NotificationDeliveryStatus::Pending->value)
                ->where('created_at', '<=', now()->subMinutes(15))
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $failed = NotificationDelivery::query()
                ->where('status', NotificationDeliveryStatus::Failed->value)
                ->where('created_at', '>=', now()->subHour())
                ->count();
        } catch (Throwable) {
            return $events;
        }

        if ($stuck > 0 || $failed > 0) {
            $events[] = [
                'type' => 'notifications_undelivered',
                'subject' => 'internal',
                'severity' => $failed > 0 ? SecurityMonitor::SEVERITY_CRITICAL : SecurityMonitor::SEVERITY_WARNING,
                'title' => 'Avisos internos sin entregar',
                'detail' => trim(($stuck > 0 ? $stuck.' esperan hace más de 15 minutos. ' : '').($failed > 0 ? $failed.' no salieron por ningún canal en la última hora.' : '')),
                'action' => 'Revise Negocios → CONFIGURACIÓN → Bitácora de notificaciones. Si esperan, confirme que el worker escuche la cola «'.config('telegram.queue').'».',
            ];
        }

        return $events;
    }

    /**
     * Una alerta sobre Telegram, sobre los avisos sin entregar o sobre la cola de
     * notificaciones no puede depender de Telegram para llegar: sale por WhatsApp.
     *
     * @param  array<string, mixed>  $event
     */
    public static function concernsTelegram(array $event): bool
    {
        $type = (string) ($event['type'] ?? '');

        if (in_array($type, ['telegram_down', 'telegram_webhook', 'notifications_undelivered', 'queue_no_workers', 'many_problems'], true)) {
            return true;
        }

        return str_starts_with($type, 'queue_') && (string) ($event['subject'] ?? '') === (string) config('telegram.queue', 'notifications');
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
                [...$event, 'channel' => 'system', 'at' => (int) ($event['at'] ?? time()), 'bypass_telegram' => self::concernsTelegram($event)],
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
