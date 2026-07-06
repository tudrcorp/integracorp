<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Models\Agent;
use App\Models\User;
use App\Services\AgentFichaPdfService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBusinessAgentFichaPdfWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $agentId,
        public string $recipientPhone,
        public int $initiatorUserId,
    ) {}

    public function handle(): void
    {
        $initiator = User::query()->find($this->initiatorUserId);

        SecurityAudit::log(
            'AUDIT_BUSINESS_AGENT_FICHA_WHATSAPP_JOB_STARTED',
            'business.agents.ficha-pdf.whatsapp.job',
            [
                'agent_id' => $this->agentId,
                'recipient_phone' => $this->recipientPhone,
                'queue' => $this->queue,
            ],
            $initiator
        );

        $agent = Agent::query()->find($this->agentId);
        if ($agent === null) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENT_FICHA_WHATSAPP_JOB_FAILED',
                'business.agents.ficha-pdf.whatsapp.job',
                [
                    'agent_id' => $this->agentId,
                    'reason' => 'agent_not_found',
                ],
                $initiator
            );

            return;
        }

        try {
            $relativePath = AgentFichaPdfService::persistForWhatsApp($agent);
            $filename = AgentFichaPdfService::filename($agent);
            $caption = AgentFichaPdfService::whatsappCaption($agent);

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
                'AUDIT_BUSINESS_AGENT_FICHA_WHATSAPP_JOB_COMPLETED',
                'business.agents.ficha-pdf.whatsapp.job',
                [
                    'agent_id' => $agent->getKey(),
                    'recipient_phone' => $this->recipientPhone,
                    'attachment_filename' => $filename,
                    'storage_relative_path' => $relativePath,
                ],
                $initiator
            );
        } catch (\Throwable $e) {
            SecurityAudit::log(
                'AUDIT_BUSINESS_AGENT_FICHA_WHATSAPP_JOB_FAILED',
                'business.agents.ficha-pdf.whatsapp.job',
                [
                    'agent_id' => $this->agentId,
                    'recipient_phone' => $this->recipientPhone,
                    'error' => $e->getMessage(),
                ],
                $initiator
            );

            throw $e;
        }
    }
}
