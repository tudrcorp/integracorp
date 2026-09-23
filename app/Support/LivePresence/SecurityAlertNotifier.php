<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Jobs\NotifyLiveSecurityAlertJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa por WhatsApp y correo cuando se detecta algo crítico. Un aviso por
 * tipo de ataque y luego silencio durante la pausa configurada: un ataque de
 * una hora no puede convertirse en cientos de mensajes.
 */
final class SecurityAlertNotifier
{
    /**
     * @param  array<string, mixed>  $event
     */
    public static function notify(array $event): void
    {
        try {
            $store = LivePresenceStore::repository();
            $type = (string) ($event['type'] ?? 'security');
            $cooldownKey = SecurityMonitor::prefix().'alert:'.$type;

            if ($store->getValue($cooldownKey) !== null) {
                return;
            }

            $minutes = max(1, (int) config('live-presence.security.alert_cooldown_minutes', 30));
            $store->putValue($cooldownKey, ['at' => time()], $minutes * 60);

            NotifyLiveSecurityAlertJob::dispatch($event);
        } catch (Throwable $exception) {
            try {
                Log::warning('LivePresence: no se pudo encolar la alerta de seguridad.', ['error' => $exception->getMessage()]);
            } catch (Throwable) {
            }
        }
    }
}
