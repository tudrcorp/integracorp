<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CompanyAssociateRegisteredAnalystMail;
use App\Models\CompanyAssociate;
use App\Models\CompanyAssociateNotificationSetting;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Companies\CompanyAssociateRegistrationNotificationMessage;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAnalystsOfCompanyAssociateRegistrationJob implements ShouldQueue
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

    public function __construct(
        public int $associateId,
    ) {}

    public function handle(): void
    {
        $associate = CompanyAssociate::query()
            ->with(['company', 'responsible'])
            ->find($this->associateId);

        if ($associate === null) {
            Log::warning('NotifyAnalystsOfCompanyAssociateRegistrationJob: asociado no encontrado', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        $settings = CompanyAssociateNotificationSetting::instance();
        $emails = $settings->emails();
        $phones = $settings->phones();

        if ($emails === [] && $phones === []) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_SKIPPED', 'company-associates.public-register.notifications', [
                'associate_id' => $associate->getKey(),
                'reason' => 'no_recipients_configured',
            ]);

            return;
        }

        $whatsappBody = CompanyAssociateRegistrationNotificationMessage::whatsappBody($associate);
        $emailPayload = CompanyAssociateRegistrationNotificationMessage::emailPayload($associate);
        $emailSubject = CompanyAssociateRegistrationNotificationMessage::emailSubject($associate);

        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_EMAIL_INVALID', 'company-associates.public-register.notifications', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                ]);

                continue;
            }

            try {
                Mail::to($email)->send(new CompanyAssociateRegisteredAnalystMail(
                    associate: $associate,
                    emailPayload: $emailPayload,
                    recipientEmail: $email,
                    subjectLine: $emailSubject,
                ));

                $emailsSent++;
            } catch (Throwable $exception) {
                Log::error('NotifyAnalystsOfCompanyAssociateRegistrationJob: error enviando email', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_EMAIL_FAILED', 'company-associates.public-register.notifications', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_WHATSAPP_INVALID', 'company-associates.public-register.notifications', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $rawPhone,
                ]);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $whatsappBody, $phone, null, [
                    'panel' => 'business',
                    'source' => 'company-associates.public-register',
                    'associate_id' => $associate->getKey(),
                ]);

                $whatsappsQueued++;
            } catch (Throwable $exception) {
                Log::error('NotifyAnalystsOfCompanyAssociateRegistrationJob: error encolando WhatsApp', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_WHATSAPP_FAILED', 'company-associates.public-register.notifications', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_DISPATCHED', 'company-associates.public-register.notifications', [
            'associate_id' => $associate->getKey(),
            'company_id' => $associate->company_id,
            'company_responsible_id' => $associate->company_responsible_id,
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_configured' => count($emails),
            'phones_configured' => count($phones),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyAnalystsOfCompanyAssociateRegistrationJob: FAILED', [
            'associate_id' => $this->associateId,
            'message' => $exception?->getMessage(),
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_FAILED', 'company-associates.public-register.notifications', [
            'associate_id' => $this->associateId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
