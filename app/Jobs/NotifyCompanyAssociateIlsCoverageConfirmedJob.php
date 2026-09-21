<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\CompanyAssociateIlsCoverageConfirmedMail;
use App\Models\CompanyAssociate;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Companies\CompanyAssociateIlsCoverageNotificationMessage;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa a los destinatarios del centro de notificaciones que un asociado de nuevos
 * negocios quedó cubierto, luego de que el analista declarara haber completado la
 * gestión. Adjunta el voucher ILS por correo y por WhatsApp.
 */
class NotifyCompanyAssociateIlsCoverageConfirmedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const AUDIT_ROUTE = 'company-associates.voucher-ils.coverage-confirmed';

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
            Log::warning('NotifyCompanyAssociateIlsCoverageConfirmedJob: asociado no encontrado', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::CompanyAssociateIlsCoverage)) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_NOTIFICATION_SKIPPED', self::AUDIT_ROUTE, [
                'associate_id' => $associate->getKey(),
                'reason' => 'notification_inactive',
            ]);

            return;
        }

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::CompanyAssociateIlsCoverage);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::CompanyAssociateIlsCoverage);

        if ($emails === [] && $phones === []) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_NOTIFICATION_SKIPPED', self::AUDIT_ROUTE, [
                'associate_id' => $associate->getKey(),
                'reason' => 'no_recipients_configured',
            ]);

            return;
        }

        $voucherPath = CompanyAssociateIlsCoverageNotificationMessage::voucherAbsolutePath($associate);
        $voucherUrl = CompanyAssociateIlsCoverageNotificationMessage::voucherPublicUrl($associate);
        $voucherFilename = CompanyAssociateIlsCoverageNotificationMessage::voucherFilename($associate);

        if ($voucherPath === null) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_VOUCHER_MISSING', self::AUDIT_ROUTE, [
                'associate_id' => $associate->getKey(),
                'document_ils' => $associate->document_ils,
            ]);
        }

        $emailsSent = $this->sendEmails($associate, $emails, $voucherPath);
        $whatsappsQueued = $this->sendWhatsApps($associate, $phones, $voucherUrl, $voucherFilename);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_NOTIFICATION_DISPATCHED', self::AUDIT_ROUTE, [
            'associate_id' => $associate->getKey(),
            'company_id' => $associate->company_id,
            'vaucher_ils' => $associate->vaucher_ils,
            'date_init' => $associate->date_init,
            'date_end' => $associate->date_end,
            'voucher_attached' => $voucherPath !== null,
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_targeted' => count($emails),
            'phones_targeted' => count($phones),
        ]);
    }

    /**
     * @param  list<string>  $emails
     */
    private function sendEmails(CompanyAssociate $associate, array $emails, ?string $voucherPath): int
    {
        if ($emails === []) {
            return 0;
        }

        $emailPayload = CompanyAssociateIlsCoverageNotificationMessage::emailPayload($associate);
        $emailSubject = CompanyAssociateIlsCoverageNotificationMessage::emailSubject($associate);
        $sent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_EMAIL_INVALID', self::AUDIT_ROUTE, [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                ]);

                continue;
            }

            try {
                Mail::to($email)->send(new CompanyAssociateIlsCoverageConfirmedMail(
                    associate: $associate,
                    emailPayload: $emailPayload,
                    recipientEmail: $email,
                    subjectLine: $emailSubject,
                    voucherPath: $voucherPath,
                ));

                $sent++;
            } catch (Throwable $exception) {
                Log::error('NotifyCompanyAssociateIlsCoverageConfirmedJob: error enviando email', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_EMAIL_FAILED', self::AUDIT_ROUTE, [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $phones
     */
    private function sendWhatsApps(
        CompanyAssociate $associate,
        array $phones,
        ?string $voucherUrl,
        ?string $voucherFilename,
    ): int {
        if ($phones === []) {
            return 0;
        }

        $body = CompanyAssociateIlsCoverageNotificationMessage::whatsappBody($associate);
        $voucherCaption = CompanyAssociateIlsCoverageNotificationMessage::whatsappVoucherCaption($associate);
        $voucherIsImage = CompanyAssociateIlsCoverageNotificationMessage::voucherIsImage($associate);
        $queued = 0;

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_WHATSAPP_INVALID', self::AUDIT_ROUTE, [
                    'associate_id' => $associate->getKey(),
                    'phone' => $rawPhone,
                ]);

                continue;
            }

            $context = [
                'panel' => 'business',
                'source' => self::AUDIT_ROUTE,
                'associate_id' => $associate->getKey(),
            ];

            try {
                $jobs = [
                    new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
                ];

                if ($voucherUrl !== null && $voucherFilename !== null) {
                    $jobs[] = $voucherIsImage
                        ? new SendNotificacionWhatsApp(
                            null,
                            $voucherCaption,
                            $phone,
                            null,
                            [...$context, 'asset' => 'voucher-ils'],
                            $voucherUrl,
                        )
                        : new SendNotificacionWhatsAppDocument(
                            null,
                            $voucherCaption,
                            $phone,
                            $voucherUrl,
                            $voucherFilename,
                            [...$context, 'asset' => 'voucher-ils'],
                        );
                }

                Bus::chain($jobs)->dispatch();

                $queued++;
            } catch (Throwable $exception) {
                Log::error('NotifyCompanyAssociateIlsCoverageConfirmedJob: error encolando WhatsApp', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_WHATSAPP_FAILED', self::AUDIT_ROUTE, [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $queued;
    }
}
