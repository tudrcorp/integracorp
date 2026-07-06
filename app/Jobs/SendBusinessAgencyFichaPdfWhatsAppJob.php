<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Models\User;
use App\Services\AgencyFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBusinessAgencyFichaPdfWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $agencyId,
        public string $recipientPhone,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_AGENCY_FICHA_WHATSAPP_JOB_STARTED',
            'business.agencies.ficha-pdf.whatsapp.job',
            [
                'agency_id' => $this->agencyId,
                'recipient_phone' => $this->recipientPhone,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $agency = Agency::query()->find($this->agencyId);
        if ($agency === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_WHATSAPP_JOB_FAILED',
                'business.agencies.ficha-pdf.whatsapp.job',
                [
                    'agency_id' => $this->agencyId,
                    'reason' => 'agency_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $relativePath = AgencyFichaPdfService::persistForWhatsApp($agency);
            $filename = AgencyFichaPdfService::filename($agency);
            $caption = AgencyFichaPdfService::whatsappCaption($agency);

            $sent = NotificationController::sendWhatsAppDocument(
                $this->recipientPhone,
                $caption,
                $relativePath,
                $filename,
            );

            if (! $sent) {
                throw new \RuntimeException('La API de WhatsApp no confirmó el envío del documento.');
            }

            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_WHATSAPP_JOB_COMPLETED',
                'business.agencies.ficha-pdf.whatsapp.job',
                [
                    'agency_id' => $agency->getKey(),
                    'recipient_phone' => $this->recipientPhone,
                    'attachment_filename' => $filename,
                    'storage_relative_path' => $relativePath,
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_WHATSAPP_JOB_FAILED',
                'business.agencies.ficha-pdf.whatsapp.job',
                [
                    'agency_id' => $this->agencyId,
                    'recipient_phone' => $this->recipientPhone,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
