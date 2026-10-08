<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EarlyRenovationAcceptedMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Renovations\EarlyRenovationNotificationPayload;
use App\Support\Renovations\EarlyRenovationRecipients;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisa a los SUPERADMIN (y a los contactos del Centro de notificaciones →
 * «Renovación anticipada») que un analista aceptó renovaciones antes del período
 * de renovación.
 *
 * Idempotente por destinatario: si el job se reintenta, no repite el correo ni
 * el WhatsApp que ya salieron.
 */
class NotifySuperAdminsOfEarlyRenovationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {
        $this->payload['delivery_key'] ??= (string) Str::uuid();
    }

    public function handle(): void
    {
        $recipients = EarlyRenovationRecipients::resolve();

        if (! $recipients['active']) {
            $this->audit('AUDIT_RENOVATION_EARLY_NOTIFICATION_SKIPPED', ['reason' => 'notification_inactive']);

            return;
        }

        if ($recipients['emails'] === [] && $recipients['phones'] === []) {
            $this->audit('AUDIT_RENOVATION_EARLY_NOTIFICATION_SKIPPED', ['reason' => 'no_recipients']);

            return;
        }

        $subject = EarlyRenovationNotificationPayload::emailSubject($this->payload);
        $whatsappBody = EarlyRenovationNotificationPayload::whatsappBody($this->payload);

        $emailsSent = 0;
        $whatsappsSent = 0;

        foreach ($recipients['emails'] as $email) {
            if ($this->alreadyDelivered('mail', $email)) {
                continue;
            }

            try {
                Mail::to($email)->send(new EarlyRenovationAcceptedMail(
                    emailPayload: $this->payload,
                    recipientEmail: $email,
                    subjectLine: $subject,
                ));

                $this->markDelivered('mail', $email);
                $emailsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifySuperAdminsOfEarlyRenovationJob: error enviando email', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($recipients['phones'] as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null || $this->alreadyDelivered('whatsapp', $phone)) {
                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatchSync(null, $whatsappBody, $phone, null, [
                    'panel' => 'business',
                    'source' => 'renovations.early-acceptance',
                    'history_ids' => $this->historyIds(),
                ]);

                $this->markDelivered('whatsapp', $phone);
                $whatsappsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifySuperAdminsOfEarlyRenovationJob: error enviando WhatsApp', [
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->audit('AUDIT_RENOVATION_EARLY_NOTIFICATION_DISPATCHED', [
            'emails_sent' => $emailsSent,
            'whatsapps_sent' => $whatsappsSent,
            'emails_configured' => count($recipients['emails']),
            'phones_configured' => count($recipients['phones']),
            'superadmins' => $recipients['superadmins'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifySuperAdminsOfEarlyRenovationJob: FAILED', [
            'history_ids' => $this->historyIds(),
            'message' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function historyIds(): array
    {
        return array_values(array_map(
            fn (array $item): int => (int) ($item['history_id'] ?? 0),
            is_array($this->payload['items'] ?? null) ? $this->payload['items'] : [],
        ));
    }

    private function deliveryCacheKey(string $channel, string $recipient): string
    {
        return 'early-renovation-notification:'.$this->payload['delivery_key'].':'.$channel.':'.sha1($recipient);
    }

    private function alreadyDelivered(string $channel, string $recipient): bool
    {
        return Cache::has($this->deliveryCacheKey($channel, $recipient));
    }

    private function markDelivered(string $channel, string $recipient): void
    {
        Cache::put($this->deliveryCacheKey($channel, $recipient), true, now()->addDay());
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(string $action, array $details): void
    {
        SecurityAudit::log($action, 'renovations.early-acceptance.notifications', [
            'kind' => $this->payload['kind'] ?? null,
            'history_ids' => $this->historyIds(),
            'authorized_by_user_id' => $this->payload['authorized_by_user_id'] ?? null,
            ...$details,
        ]);
    }
}
