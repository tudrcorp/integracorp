<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Jobs\SendNotificacionWhatsApp;
use App\Jobs\SendNotificacionWhatsAppDocument;
use App\Mail\CompanyAssociateDocumentsMail;
use App\Models\CompanyAssociate;
use App\Models\CompanyAssociateNotificationSetting;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CompanyAssociateDocumentsDeliverer
{
    /**
     * @param  array{filename: string, preview_url: string, absolute_path: string}  $carnet
     */
    public static function deliver(CompanyAssociate $associate, array $carnet): void
    {
        $qrAbsolutePath = CompanyAssociateInclusionQrCatalog::qrExists()
            ? \Illuminate\Support\Facades\Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::qrStoragePath())
            : null;

        $attachmentPaths = array_values(array_filter([
            $carnet['absolute_path'],
            is_string($qrAbsolutePath) && is_file($qrAbsolutePath) ? $qrAbsolutePath : null,
        ]));

        if ($attachmentPaths === []) {
            throw new \RuntimeException('No hay documentos disponibles para enviar al asociado.');
        }

        $emailRecipients = self::emailRecipients($associate);
        $phoneRecipients = self::phoneRecipients($associate);

        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($emailRecipients as $email => $recipientName) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_EMAIL_INVALID', 'company-associates.public-register.documents', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                ]);

                continue;
            }

            try {
                Mail::to($email)->send(new CompanyAssociateDocumentsMail(
                    associate: $associate,
                    recipientEmail: $email,
                    recipientName: $recipientName,
                    attachmentPaths: $attachmentPaths,
                    subjectLine: CompanyAssociateDocumentsDeliveryMessage::emailSubject($associate),
                ));

                $emailsSent++;
            } catch (Throwable $exception) {
                Log::error('CompanyAssociateDocumentsDeliverer: error enviando email', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_EMAIL_FAILED', 'company-associates.public-register.documents', [
                    'associate_id' => $associate->getKey(),
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $carnetPublicUrl = asset('storage/tarjeta-afiliacion/'.$carnet['filename']);
        $qrPublicUrl = CompanyAssociateInclusionQrCatalog::qrPublicUrl();

        foreach ($phoneRecipients as $rawPhone => $recipientName) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_WHATSAPP_INVALID', 'company-associates.public-register.documents', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $rawPhone,
                ]);

                continue;
            }

            try {
                $context = [
                    'panel' => 'business',
                    'source' => 'company-associates.public-register.documents',
                    'associate_id' => $associate->getKey(),
                    'recipient_name' => $recipientName,
                ];

                $jobs = [
                    new SendNotificacionWhatsApp(
                        null,
                        CompanyAssociateDocumentsDeliveryMessage::whatsappIntro($associate),
                        $phone,
                        null,
                        $context,
                    ),
                    new SendNotificacionWhatsAppDocument(
                        null,
                        CompanyAssociateDocumentsDeliveryMessage::whatsappCarnetCaption($associate),
                        $phone,
                        $carnetPublicUrl,
                        $carnet['filename'],
                        [...$context, 'asset' => 'carnet'],
                    ),
                ];

                if (CompanyAssociateInclusionQrCatalog::qrExists()) {
                    $jobs[] = new SendNotificacionWhatsApp(
                        null,
                        CompanyAssociateDocumentsDeliveryMessage::whatsappQrCaption($associate),
                        $phone,
                        null,
                        [...$context, 'asset' => 'inclusion-qr'],
                        $qrPublicUrl,
                    );
                }

                Bus::chain($jobs)->dispatch();

                $whatsappsQueued++;
            } catch (Throwable $exception) {
                Log::error('CompanyAssociateDocumentsDeliverer: error encolando WhatsApp', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_WHATSAPP_FAILED', 'company-associates.public-register.documents', [
                    'associate_id' => $associate->getKey(),
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_DISPATCHED', 'company-associates.public-register.documents', [
            'associate_id' => $associate->getKey(),
            'company_id' => $associate->company_id,
            'carnet_filename' => $carnet['filename'],
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_targeted' => count($emailRecipients),
            'phones_targeted' => count($phoneRecipients),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function emailRecipients(CompanyAssociate $associate): array
    {
        $recipients = [];

        if (filled($associate->email)) {
            $recipients[strtolower(trim((string) $associate->email))] = $associate->full_name;
        }

        foreach (CompanyAssociateNotificationSetting::instance()->emails() as $email) {
            $normalized = strtolower(trim($email));

            if ($normalized !== '') {
                $recipients[$normalized] ??= 'Analista INTEGRACORP';
            }
        }

        return $recipients;
    }

    /**
     * @return array<string, string>
     */
    private static function phoneRecipients(CompanyAssociate $associate): array
    {
        $recipients = [];

        if (filled($associate->phone)) {
            $recipients[trim((string) $associate->phone)] = $associate->full_name;
        }

        foreach (CompanyAssociateNotificationSetting::instance()->phones() as $phone) {
            $normalized = trim($phone);

            if ($normalized !== '') {
                $recipients[$normalized] ??= 'Analista INTEGRACORP';
            }
        }

        return $recipients;
    }
}
