<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Models\TravelAgency;
use App\Models\User;
use App\Services\TravelAgencyFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBusinessTravelAgencyFichaPdfWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $travelAgencyId,
        public string $recipientPhone,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_WHATSAPP_JOB_STARTED',
            'business.travel-agencies.ficha-pdf.whatsapp.job',
            [
                'travel_agency_id' => $this->travelAgencyId,
                'recipient_phone' => $this->recipientPhone,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $travelAgency = TravelAgency::query()->find($this->travelAgencyId);
        if ($travelAgency === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_WHATSAPP_JOB_FAILED',
                'business.travel-agencies.ficha-pdf.whatsapp.job',
                [
                    'travel_agency_id' => $this->travelAgencyId,
                    'reason' => 'travel_agency_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $relativePath = TravelAgencyFichaPdfService::persistForWhatsApp($travelAgency);
            $filename = TravelAgencyFichaPdfService::filename($travelAgency);
            $caption = TravelAgencyFichaPdfService::whatsappCaption($travelAgency);

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
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_WHATSAPP_JOB_COMPLETED',
                'business.travel-agencies.ficha-pdf.whatsapp.job',
                [
                    'travel_agency_id' => $travelAgency->getKey(),
                    'recipient_phone' => $this->recipientPhone,
                    'attachment_filename' => $filename,
                    'storage_relative_path' => $relativePath,
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_WHATSAPP_JOB_FAILED',
                'business.travel-agencies.ficha-pdf.whatsapp.job',
                [
                    'travel_agency_id' => $this->travelAgencyId,
                    'recipient_phone' => $this->recipientPhone,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
