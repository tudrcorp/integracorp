<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\TdevRegisteredAnalystMail;
use App\Models\TdevAgency;
use App\Models\TdevAgent;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use App\Support\Tdev\TdevRegistrationNotificationMessage;
use App\Support\Tdev\TdevWhatsAppBrandImage;
use App\Support\WhatsAppBrandImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAnalystsOfTdevRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RECORD_AGENCY = 'agency';

    public const RECORD_AGENT = 'agent';

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public string $recordType,
        public int $recordId,
    ) {}

    public function handle(): void
    {
        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::TdevRegistration)) {
            SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_SKIPPED', 'tdev.public-register.notifications', [
                'record_type' => $this->recordType,
                'record_id' => $this->recordId,
                'reason' => 'notification_inactive',
            ]);

            return;
        }

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::TdevRegistration);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::TdevRegistration);

        if ($emails === [] && $phones === []) {
            SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_SKIPPED', 'tdev.public-register.notifications', [
                'record_type' => $this->recordType,
                'record_id' => $this->recordId,
                'reason' => 'no_recipients_configured',
            ]);

            return;
        }

        [$whatsappBody, $emailPayload, $emailSubject] = $this->buildMessages();

        if ($whatsappBody === null) {
            return;
        }

        $emailsSent = 0;
        $whatsappsQueued = 0;
        $brandImageUrl = TdevWhatsAppBrandImage::publicUrl();

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_EMAIL_INVALID', 'tdev.public-register.notifications', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'email' => $email,
                ]);

                continue;
            }

            try {
                Mail::to($email)->send(new TdevRegisteredAnalystMail(
                    emailPayload: $emailPayload,
                    recipientEmail: $email,
                    subjectLine: $emailSubject,
                ));

                $emailsSent++;

                Log::info('NotifyAnalystsOfTdevRegistrationJob: email enviado', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'email' => $email,
                ]);
            } catch (Throwable $exception) {
                Log::error('NotifyAnalystsOfTdevRegistrationJob: error enviando email', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_EMAIL_FAILED', 'tdev.public-register.notifications', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_WHATSAPP_INVALID', 'tdev.public-register.notifications', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'phone' => $rawPhone,
                ]);

                continue;
            }

            try {
                $this->sendWhatsAppWithBrandFallback($whatsappBody, $phone, $brandImageUrl);

                $whatsappsQueued++;
            } catch (Throwable $exception) {
                Log::error('NotifyAnalystsOfTdevRegistrationJob: error encolando WhatsApp', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_WHATSAPP_FAILED', 'tdev.public-register.notifications', [
                    'record_type' => $this->recordType,
                    'record_id' => $this->recordId,
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_DISPATCHED', 'tdev.public-register.notifications', [
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_configured' => count($emails),
            'phones_configured' => count($phones),
        ]);
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>, 2: string}
     */
    private function buildMessages(): array
    {
        if ($this->recordType === self::RECORD_AGENCY) {
            $agency = TdevAgency::query()
                ->with(['parentAgency', 'country', 'state', 'city'])
                ->find($this->recordId);

            if ($agency === null) {
                Log::warning('NotifyAnalystsOfTdevRegistrationJob: agencia no encontrada', [
                    'record_id' => $this->recordId,
                ]);

                return [null, [], ''];
            }

            return [
                TdevRegistrationNotificationMessage::whatsappBodyForAgency($agency),
                TdevRegistrationNotificationMessage::emailPayloadForAgency($agency),
                TdevRegistrationNotificationMessage::emailSubjectForAgency($agency),
            ];
        }

        $agent = TdevAgent::query()
            ->with(['agency.parentAgency', 'agency.country', 'agency.state', 'agency.city'])
            ->find($this->recordId);

        if ($agent === null) {
            Log::warning('NotifyAnalystsOfTdevRegistrationJob: agente no encontrado', [
                'record_id' => $this->recordId,
            ]);

            return [null, [], ''];
        }

        return [
            TdevRegistrationNotificationMessage::whatsappBodyForAgent($agent),
            TdevRegistrationNotificationMessage::emailPayloadForAgent($agent),
            TdevRegistrationNotificationMessage::emailSubjectForAgent($agent),
        ];
    }

    private function sendWhatsAppWithBrandFallback(string $whatsappBody, string $phone, string $brandImageUrl): void
    {
        $auditContext = [
            'panel' => 'business',
            'source' => 'tdev.public-register',
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
        ];

        try {
            SendNotificacionWhatsApp::dispatchSync(null, $whatsappBody, $phone, null, $auditContext, $brandImageUrl);

            return;
        } catch (Throwable $exception) {
            $fallbackImageUrl = WhatsAppBrandImage::publicUrl();

            if ($fallbackImageUrl === $brandImageUrl) {
                throw $exception;
            }

            Log::warning('NotifyAnalystsOfTdevRegistrationJob: banner TDEV no disponible, reintento con brand Integracorp', [
                'record_type' => $this->recordType,
                'record_id' => $this->recordId,
                'phone' => $phone,
                'primary_image' => $brandImageUrl,
                'fallback_image' => $fallbackImageUrl,
                'error' => $exception->getMessage(),
            ]);

            SendNotificacionWhatsApp::dispatchSync(null, $whatsappBody, $phone, null, [
                ...$auditContext,
                'image_fallback' => true,
            ], $fallbackImageUrl);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyAnalystsOfTdevRegistrationJob: FAILED', [
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
            'message' => $exception?->getMessage(),
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_TDEV_NOTIFICATION_FAILED', 'tdev.public-register.notifications', [
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
