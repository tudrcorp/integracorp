<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agents\Concerns;

use App\Jobs\SendBusinessAgentFichaPdfMailJob;
use App\Jobs\SendBusinessAgentFichaPdfWhatsAppJob;
use App\Models\Agent;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\BusinessAgentFichaPdfAccess;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait QueuesAgentFichaPdfEmail
{
    public function queueAgentFichaPdfEmail(int $agentId, string $email): void
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title('Correo inválido')
                ->body('Indique una dirección de correo válida.')
                ->danger()
                ->send();

            return;
        }

        $agent = Agent::query()->find($agentId);
        if ($agent === null) {
            Notification::make()
                ->title('Agente no encontrado')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessAgentFichaPdfAccess::userCanAccess($agent)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_ACCESS_DENIED', 'business.agents.ficha-pdf.email.livewire', [
                'agent_id' => $agentId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de este agente.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessAgentFichaPdfMailJob::dispatch(
            (int) $agent->getKey(),
            $email,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_EMAIL_QUEUED', 'business.agents.ficha-pdf.email.livewire', [
            'agent_id' => $agent->getKey(),
            'agent_name' => $agent->name,
            'recipient_email' => $email,
        ]);

        Notification::make()
            ->title('Correo encolado')
            ->body('El envío con el PDF adjunto se procesará en segundo plano.')
            ->success()
            ->send();
    }

    public function queueAgentFichaPdfWhatsApp(int $agentId, string $phone): void
    {
        $phone = trim($phone);
        if ($phone === '') {
            Notification::make()
                ->title('Teléfono requerido')
                ->body('Indique el número WhatsApp del destinatario.')
                ->danger()
                ->send();

            return;
        }

        if (HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone) === null) {
            Notification::make()
                ->title('Teléfono inválido')
                ->body('Use un número válido con WhatsApp (ej. 04127018390 o +584121234567).')
                ->danger()
                ->send();

            return;
        }

        $agent = Agent::query()->find($agentId);
        if ($agent === null) {
            Notification::make()
                ->title('Agente no encontrado')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessAgentFichaPdfAccess::userCanAccess($agent)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_ACCESS_DENIED', 'business.agents.ficha-pdf.whatsapp.livewire', [
                'agent_id' => $agentId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de este agente.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessAgentFichaPdfWhatsAppJob::dispatch(
            (int) $agent->getKey(),
            $phone,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_WHATSAPP_QUEUED', 'business.agents.ficha-pdf.whatsapp.livewire', [
            'agent_id' => $agent->getKey(),
            'agent_name' => $agent->name,
            'recipient_phone' => $phone,
        ]);

        Notification::make()
            ->title('WhatsApp encolado')
            ->body('El PDF de la ficha se enviará por la API de WhatsApp en segundo plano.')
            ->success()
            ->send();
    }
}
