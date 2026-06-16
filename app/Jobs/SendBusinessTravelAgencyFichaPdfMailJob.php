<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BusinessTravelAgencyFichaPdfMail;
use App\Models\TravelAgency;
use App\Models\User;
use App\Services\TravelAgencyFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBusinessTravelAgencyFichaPdfMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $travelAgencyId,
        public string $recipientEmail,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_EMAIL_JOB_STARTED',
            'business.travel-agencies.ficha-pdf.email.job',
            [
                'travel_agency_id' => $this->travelAgencyId,
                'recipient_email' => $this->recipientEmail,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $travelAgency = TravelAgency::query()->find($this->travelAgencyId);
        if ($travelAgency === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_EMAIL_JOB_FAILED',
                'business.travel-agencies.ficha-pdf.email.job',
                [
                    'travel_agency_id' => $this->travelAgencyId,
                    'reason' => 'travel_agency_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $binary = TravelAgencyFichaPdfService::outputBinary($travelAgency);
            $filename = TravelAgencyFichaPdfService::filename($travelAgency);
            $codeLabel = TravelAgencyFichaPdfService::codeLabel($travelAgency);

            Mail::to($this->recipientEmail)
                ->send(new BusinessTravelAgencyFichaPdfMail(
                    travelAgencyDisplayName: (string) $travelAgency->name,
                    travelAgencyCodeLabel: $codeLabel,
                    pdfBinary: $binary,
                    attachmentFilename: $filename,
                ));

            SecurityAudit::log(
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_EMAIL_JOB_COMPLETED',
                'business.travel-agencies.ficha-pdf.email.job',
                [
                    'travel_agency_id' => $travelAgency->getKey(),
                    'recipient_email' => $this->recipientEmail,
                    'attachment_filename' => $filename,
                    'bytes' => strlen($binary),
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_EMAIL_JOB_FAILED',
                'business.travel-agencies.ficha-pdf.email.job',
                [
                    'travel_agency_id' => $this->travelAgencyId,
                    'recipient_email' => $this->recipientEmail,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
