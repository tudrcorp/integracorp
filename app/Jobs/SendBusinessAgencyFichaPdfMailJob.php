<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BusinessAgencyFichaPdfMail;
use App\Models\Agency;
use App\Models\User;
use App\Services\AgencyFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBusinessAgencyFichaPdfMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $agencyId,
        public string $recipientEmail,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_JOB_STARTED',
            'business.agencies.ficha-pdf.email.job',
            [
                'agency_id' => $this->agencyId,
                'recipient_email' => $this->recipientEmail,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $agency = Agency::query()->find($this->agencyId);
        if ($agency === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_JOB_FAILED',
                'business.agencies.ficha-pdf.email.job',
                [
                    'agency_id' => $this->agencyId,
                    'reason' => 'agency_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $binary = AgencyFichaPdfService::outputBinary($agency);
            $filename = AgencyFichaPdfService::filename($agency);
            $codeLabel = AgencyFichaPdfService::codeLabel($agency);

            Mail::to($this->recipientEmail)
                ->send(new BusinessAgencyFichaPdfMail(
                    agencyDisplayName: (string) $agency->name_corporative,
                    agencyCodeLabel: $codeLabel,
                    pdfBinary: $binary,
                    attachmentFilename: $filename,
                ));

            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_JOB_COMPLETED',
                'business.agencies.ficha-pdf.email.job',
                [
                    'agency_id' => $agency->getKey(),
                    'recipient_email' => $this->recipientEmail,
                    'attachment_filename' => $filename,
                    'bytes' => strlen($binary),
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_JOB_FAILED',
                'business.agencies.ficha-pdf.email.job',
                [
                    'agency_id' => $this->agencyId,
                    'recipient_email' => $this->recipientEmail,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
