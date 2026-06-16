<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Concerns;

use App\Jobs\SendBusinessTravelAgencyFichaPdfMailJob;
use App\Jobs\SendBusinessTravelAgencyFichaPdfWhatsAppJob;
use App\Models\TravelAgency;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\BusinessTravelAgencyFichaPdfAccess;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait QueuesTravelAgencyFichaPdfSharing
{
    public function queueTravelAgencyFichaPdfEmail(int $travelAgencyId, string $email): void
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

        $travelAgency = TravelAgency::query()->find($travelAgencyId);
        if ($travelAgency === null) {
            Notification::make()
                ->title('Agencia de viajes no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessTravelAgencyFichaPdfAccess::userCanAccess($travelAgency)) {
            SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_ACCESS_DENIED', 'business.travel-agencies.ficha-pdf.email.livewire', [
                'travel_agency_id' => $travelAgencyId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de esta agencia de viajes.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessTravelAgencyFichaPdfMailJob::dispatch(
            (int) $travelAgency->getKey(),
            $email,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_EMAIL_QUEUED', 'business.travel-agencies.ficha-pdf.email.livewire', [
            'travel_agency_id' => $travelAgency->getKey(),
            'travel_agency_name' => $travelAgency->name,
            'recipient_email' => $email,
        ]);

        Notification::make()
            ->title('Correo encolado')
            ->body('El envío con el PDF adjunto se procesará en segundo plano.')
            ->success()
            ->send();
    }

    public function queueTravelAgencyFichaPdfWhatsApp(int $travelAgencyId, string $phone): void
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

        $travelAgency = TravelAgency::query()->find($travelAgencyId);
        if ($travelAgency === null) {
            Notification::make()
                ->title('Agencia de viajes no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessTravelAgencyFichaPdfAccess::userCanAccess($travelAgency)) {
            SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_ACCESS_DENIED', 'business.travel-agencies.ficha-pdf.whatsapp.livewire', [
                'travel_agency_id' => $travelAgencyId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de esta agencia de viajes.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessTravelAgencyFichaPdfWhatsAppJob::dispatch(
            (int) $travelAgency->getKey(),
            $phone,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_WHATSAPP_QUEUED', 'business.travel-agencies.ficha-pdf.whatsapp.livewire', [
            'travel_agency_id' => $travelAgency->getKey(),
            'travel_agency_name' => $travelAgency->name,
            'recipient_phone' => $phone,
        ]);

        Notification::make()
            ->title('WhatsApp encolado')
            ->body('El PDF de la ficha se enviará por la API de WhatsApp en segundo plano.')
            ->success()
            ->send();
    }
}
