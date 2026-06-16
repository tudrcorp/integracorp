<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agencies\Concerns;

use App\Jobs\SendBusinessAgencyFichaPdfMailJob;
use App\Jobs\SendBusinessAgencyFichaPdfWhatsAppJob;
use App\Models\Agency;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\BusinessAgencyFichaPdfAccess;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait QueuesAgencyFichaPdfEmail
{
    public function queueAgencyFichaPdfEmail(int $agencyId, string $email): void
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

        $agency = Agency::query()->find($agencyId);
        if ($agency === null) {
            Notification::make()
                ->title('Agencia no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessAgencyFichaPdfAccess::userCanAccess($agency)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_ACCESS_DENIED', 'business.agencies.ficha-pdf.email.livewire', [
                'agency_id' => $agencyId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de esta agencia.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessAgencyFichaPdfMailJob::dispatch(
            (int) $agency->getKey(),
            $email,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_QUEUED', 'business.agencies.ficha-pdf.email.livewire', [
            'agency_id' => $agency->getKey(),
            'agency_name' => $agency->name_corporative,
            'recipient_email' => $email,
        ]);

        Notification::make()
            ->title('Correo encolado')
            ->body('El envío con el PDF adjunto se procesará en segundo plano.')
            ->success()
            ->send();
    }

    public function queueAgencyFichaPdfWhatsApp(int $agencyId, string $phone): void
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

        $agency = Agency::query()->find($agencyId);
        if ($agency === null) {
            Notification::make()
                ->title('Agencia no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessAgencyFichaPdfAccess::userCanAccess($agency)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_ACCESS_DENIED', 'business.agencies.ficha-pdf.whatsapp.livewire', [
                'agency_id' => $agencyId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de esta agencia.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessAgencyFichaPdfWhatsAppJob::dispatch(
            (int) $agency->getKey(),
            $phone,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_WHATSAPP_QUEUED', 'business.agencies.ficha-pdf.whatsapp.livewire', [
            'agency_id' => $agency->getKey(),
            'agency_name' => $agency->name_corporative,
            'recipient_phone' => $phone,
        ]);

        Notification::make()
            ->title('WhatsApp encolado')
            ->body('El PDF de la ficha se enviará por la API de WhatsApp en segundo plano.')
            ->success()
            ->send();
    }
}
