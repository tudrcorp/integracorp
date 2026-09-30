<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\AffiliateUpdatedByOperationsMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Operations\AffiliateUpdateNotificationMessage;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
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
 * Avisa al equipo de Afiliaciones (Centro de notificaciones → «Actualización de
 * afiliados (Operaciones)») cada vez que Operaciones cambia datos personales de
 * un afiliado individual.
 *
 * Idempotente por destinatario: si el job se reintenta, no repite el correo ni
 * el WhatsApp que ya salieron.
 */
class NotifyAffiliationsTeamOfAffiliateUpdateJob implements ShouldQueue
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
        $key = SystemNotificationKey::OperationsAffiliateUpdate;

        if (! SystemNotificationRecipients::isActive($key)) {
            $this->audit('AUDIT_OPERATIONS_AFFILIATE_UPDATE_NOTIFICATION_SKIPPED', ['reason' => 'notification_inactive']);

            return;
        }

        $emails = SystemNotificationRecipients::emails($key);
        $phones = SystemNotificationRecipients::phones($key);

        if ($emails === [] && $phones === []) {
            $this->audit('AUDIT_OPERATIONS_AFFILIATE_UPDATE_NOTIFICATION_SKIPPED', ['reason' => 'no_recipients_configured']);

            return;
        }

        $subject = AffiliateUpdateNotificationMessage::emailSubject($this->payload);
        $whatsappBody = AffiliateUpdateNotificationMessage::whatsappBody($this->payload);

        $emailsSent = 0;
        $whatsappsSent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $this->alreadyDelivered('mail', $email)) {
                continue;
            }

            try {
                Mail::to($email)->send(new AffiliateUpdatedByOperationsMail(
                    emailPayload: $this->payload,
                    recipientEmail: $email,
                    subjectLine: $subject,
                ));

                $this->markDelivered('mail', $email);
                $emailsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifyAffiliationsTeamOfAffiliateUpdateJob: error enviando email', [
                    'affiliate_id' => $this->payload['affiliate_id'] ?? null,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null || $this->alreadyDelivered('whatsapp', $phone)) {
                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatchSync(null, $whatsappBody, $phone, null, [
                    'panel' => 'operations',
                    'source' => 'operations.affiliate-personal-data',
                    'affiliate_id' => $this->payload['affiliate_id'] ?? null,
                ]);

                $this->markDelivered('whatsapp', $phone);
                $whatsappsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifyAffiliationsTeamOfAffiliateUpdateJob: error enviando WhatsApp', [
                    'affiliate_id' => $this->payload['affiliate_id'] ?? null,
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->audit('AUDIT_OPERATIONS_AFFILIATE_UPDATE_NOTIFICATION_DISPATCHED', [
            'emails_sent' => $emailsSent,
            'whatsapps_sent' => $whatsappsSent,
            'emails_configured' => count($emails),
            'phones_configured' => count($phones),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyAffiliationsTeamOfAffiliateUpdateJob: FAILED', [
            'affiliate_id' => $this->payload['affiliate_id'] ?? null,
            'message' => $exception?->getMessage(),
        ]);
    }

    private function deliveryCacheKey(string $channel, string $recipient): string
    {
        return 'affiliate-update-notification:'.$this->payload['delivery_key'].':'.$channel.':'.sha1($recipient);
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
        SecurityAudit::log($action, 'operations.affiliates.personal-data.notifications', [
            'affiliate_id' => $this->payload['affiliate_id'] ?? null,
            'affiliation_code' => $this->payload['affiliation_code'] ?? null,
            ...$details,
        ]);
    }
}
