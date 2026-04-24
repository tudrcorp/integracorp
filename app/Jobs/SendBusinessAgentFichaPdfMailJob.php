<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BusinessAgentFichaPdfMail;
use App\Models\Agent;
use App\Models\User;
use App\Services\AgentFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBusinessAgentFichaPdfMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $agentId,
        public string $recipientEmail,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_AGENT_FICHA_EMAIL_JOB_STARTED',
            'business.agents.ficha-pdf.email.job',
            [
                'agent_id' => $this->agentId,
                'recipient_email' => $this->recipientEmail,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $agent = Agent::query()->find($this->agentId);
        if ($agent === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENT_FICHA_EMAIL_JOB_FAILED',
                'business.agents.ficha-pdf.email.job',
                [
                    'agent_id' => $this->agentId,
                    'reason' => 'agent_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $binary = AgentFichaPdfService::outputBinary($agent);
            $filename = AgentFichaPdfService::filename($agent);
            $code = 'AGT-000'.$agent->getKey();

            Mail::to($this->recipientEmail)
                ->send(new BusinessAgentFichaPdfMail(
                    agentDisplayName: (string) $agent->name,
                    agentCodeLabel: $code,
                    pdfBinary: $binary,
                    attachmentFilename: $filename,
                ));

            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENT_FICHA_EMAIL_JOB_COMPLETED',
                'business.agents.ficha-pdf.email.job',
                [
                    'agent_id' => $agent->getKey(),
                    'recipient_email' => $this->recipientEmail,
                    'attachment_filename' => $filename,
                    'bytes' => strlen($binary),
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENT_FICHA_EMAIL_JOB_FAILED',
                'business.agents.ficha-pdf.email.job',
                [
                    'agent_id' => $this->agentId,
                    'recipient_email' => $this->recipientEmail,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
