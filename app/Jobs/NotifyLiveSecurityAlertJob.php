<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\LiveSecurityAlertMail;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Aviso del monitor en vivo: ataques (Alertas de seguridad) o problemas de
 * colas y errores (Alertas de colas y errores).
 *
 * Destinatarios: los contactos de la entrada del Centro de notificaciones más
 * los usuarios de la lista blanca del monitor. Si la entrada está pausada no se
 * envía nada; el evento igual queda en el monitor.
 *
 * Los avisos de colas se ejecutan con dispatchSync y $sendWhatsAppNow: si las
 * colas están caídas, un aviso encolado nunca llegaría.
 */
class NotifyLiveSecurityAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        public array $event,
        public string $notificationKey = 'live_security_alert',
        public bool $sendWhatsAppNow = false,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(): void
    {
        $key = SystemNotificationKey::tryFrom($this->notificationKey) ?? SystemNotificationKey::LiveSecurityAlert;
        $isSystem = $key === SystemNotificationKey::LiveSystemAlert;

        if (! SystemNotificationRecipients::isActive($key)) {
            return;
        }

        $users = User::query()
            ->whereIn('email', (array) config('live-presence.allowed_emails', []))
            ->where('status', 'ACTIVO')
            ->get(['id', 'email', 'phone']);

        $emails = self::uniqueEmails([...SystemNotificationRecipients::emails($key), ...$users->pluck('email')->all()]);
        $phones = self::uniquePhones([...SystemNotificationRecipients::phones($key), ...$users->pluck('phone')->all()]);

        $subject = ($isSystem ? '⚠️ ' : '🚨 ').(string) ($this->event['title'] ?? ($isSystem ? 'Alerta de colas y errores' : 'Alerta de seguridad')).' · IntegraCorp';
        $sent = 0;

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new LiveSecurityAlertMail($this->event, $subject));
                $sent++;
            } catch (Throwable $exception) {
                Log::error('NotifyLiveSecurityAlertJob: error enviando correo', ['email' => $email, 'error' => $exception->getMessage()]);
            }
        }

        $body = self::whatsappBody($this->event);

        foreach ($phones as $phone) {
            $context = [
                'panel' => 'business',
                'source' => $isSystem ? 'live-presence.system-alert' : 'live-presence.security-alert',
                'event_type' => $this->event['type'] ?? null,
            ];

            try {
                if ($this->sendWhatsAppNow) {
                    SendNotificacionWhatsApp::dispatchSync(null, $body, $phone, null, $context);
                } else {
                    SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, $context);
                }
            } catch (Throwable $exception) {
                Log::error('NotifyLiveSecurityAlertJob: error enviando WhatsApp', ['phone' => $phone, 'error' => $exception->getMessage()]);
            }
        }

        SecurityAudit::log($isSystem ? 'AUDIT_LIVE_SYSTEM_ALERT_SENT' : 'AUDIT_LIVE_SECURITY_ALERT_SENT', $isSystem ? 'live-presence.system-alert' : 'live-presence.security-alert', [
            'event' => $this->event,
            'emails_sent' => $sent,
            'whatsapps_queued' => count($phones),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function whatsappBody(array $event): string
    {
        $isSystem = ($event['channel'] ?? null) === 'system';
        $lines = [
            ($isSystem ? '⚠️' : '🚨').' *'.(string) ($event['title'] ?? 'Alerta de seguridad').'*',
            '',
            (string) ($event['detail'] ?? ''),
        ];

        if (! empty($event['ip'])) {
            $lines[] = 'IP: '.$event['ip'];
        }

        if (! empty($event['account'])) {
            $lines[] = 'Cuenta: '.$event['account'];
        }

        $lines[] = 'Hora: '.date('d/m/Y H:i:s', (int) ($event['at'] ?? time()));
        $lines[] = '';
        if (! empty($event['action'])) {
            $lines[] = 'Qué hacer: '.$event['action'];
        }

        $lines[] = $isSystem
            ? 'Revise INTEGRACORP → Negocios → Colas y errores. No se repetirá este aviso durante '.(int) config('live-presence.security.alert_cooldown_minutes', 30).' min.'
            : 'Revise el Monitor en vivo en INTEGRACORP → Negocios. No se repetirá este aviso durante '.(int) config('live-presence.security.alert_cooldown_minutes', 30).' min.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return list<string>
     */
    private static function uniqueEmails(array $emails): array
    {
        $unique = [];

        foreach ($emails as $email) {
            $email = mb_strtolower(trim((string) $email));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $unique[$email] = $email;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, mixed>  $phones
     * @return list<string>
     */
    private static function uniquePhones(array $phones): array
    {
        $unique = [];

        foreach ($phones as $phone) {
            $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp(is_scalar($phone) ? (string) $phone : null);

            if ($normalized !== null) {
                $unique[$normalized] = $normalized;
            }
        }

        return array_values($unique);
    }
}
