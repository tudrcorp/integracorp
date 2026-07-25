<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\TelemedicineCaseReversedMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use App\Support\Telemedicine\TelemedicineCaseReversalNotificationMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyTelemedicineCaseReversalJob implements ShouldQueue
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
    ) {}

    public function handle(): void
    {
        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::TelemedicineCaseReversal)) {
            SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_REVERSAL_NOTIFICATION_SKIPPED', 'telemedicina.cases.reverse.notifications', [
                'telemedicine_case_code' => $this->payload['case_code'] ?? null,
                'reason' => 'notification_inactive',
            ]);

            return;
        }

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::TelemedicineCaseReversal);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::TelemedicineCaseReversal);

        if ($emails === [] && $phones === []) {
            SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_REVERSAL_NOTIFICATION_SKIPPED', 'telemedicina.cases.reverse.notifications', [
                'telemedicine_case_code' => $this->payload['case_code'] ?? null,
                'reason' => 'no_recipients_configured',
            ]);

            return;
        }

        $whatsappBody = TelemedicineCaseReversalNotificationMessage::whatsappBody($this->payload);
        $emailPayload = TelemedicineCaseReversalNotificationMessage::emailPayload($this->payload);
        $emailSubject = TelemedicineCaseReversalNotificationMessage::emailSubject($this->payload);

        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new TelemedicineCaseReversedMail(
                    emailPayload: $emailPayload,
                    recipientEmail: $email,
                    subjectLine: $emailSubject,
                ));

                $emailsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifyTelemedicineCaseReversalJob: error enviando email', [
                    'case_code' => $this->payload['case_code'] ?? null,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatchSync(null, $whatsappBody, $phone, null, [
                    'panel' => 'telemedicina',
                    'source' => 'telemedicine.case-reversal',
                    'case_code' => $this->payload['case_code'] ?? null,
                ]);

                $whatsappsQueued++;
            } catch (Throwable $exception) {
                Log::error('NotifyTelemedicineCaseReversalJob: error enviando WhatsApp', [
                    'case_code' => $this->payload['case_code'] ?? null,
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_REVERSAL_NOTIFICATION_DISPATCHED', 'telemedicina.cases.reverse.notifications', [
            'telemedicine_case_code' => $this->payload['case_code'] ?? null,
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_configured' => count($emails),
            'phones_configured' => count($phones),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyTelemedicineCaseReversalJob: FAILED', [
            'case_code' => $this->payload['case_code'] ?? null,
            'message' => $exception?->getMessage(),
        ]);
    }
}
