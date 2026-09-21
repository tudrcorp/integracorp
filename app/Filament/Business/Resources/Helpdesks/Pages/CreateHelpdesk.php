<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Concerns\AssertsHelpdeskTicketCreationAccess;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Support\HelpdeskBusinessScrumWorkflow;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    use AssertsHelpdeskTicketCreationAccess;
    use DispatchesHelpdeskCreateNotifications;
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;

    protected static string $resource = HelpdeskResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->prepareHelpdeskColaboradorAssigneesForCreate($data);
        $data = HelpdeskBusinessScrumWorkflow::queueForProductOwner($data);
        $this->helpdeskColaboradorIdsPendingValidation = HelpdeskBusinessScrumWorkflow::normalizeAssigneeIds(
            $data['rrhhColaboradores'] ?? []
        );

        return $data;
    }

    protected function beforeCreate(): void
    {
        $this->assertHelpdeskCreationAllowedOrHalt();

        if (HelpdeskBusinessScrumWorkflow::isQueuedForProductOwner([
            'rrhhColaboradores' => $this->helpdeskColaboradorIdsPendingValidation,
        ])) {
            $error = HelpdeskBusinessScrumWorkflow::productOwnerReadinessError();
            if ($error !== null) {
                Notification::make()
                    ->title('No se puede crear el ticket')
                    ->body($error)
                    ->icon('heroicon-m-no-symbol')
                    ->iconColor('danger')
                    ->danger()
                    ->send();
                $this->halt();
            }
        }

        $this->validatePendingHelpdeskColaboradorAssigneesOrHalt();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $ticket = $this->getRecord();
        $ticket->unsetRelation('rrhhColaboradores');
        $ticket->load('rrhhColaboradores');

        $isScrumBacklog = HelpdeskBusinessScrumWorkflow::ticketIsProductOwnerInbox($ticket);

        if ($isScrumBacklog) {
            HelpdeskBusinessScrumWorkflow::syncProductOwnerInbox($ticket);
        }

        $this->dispatchHelpdeskCreateNotifications($ticket, 'business');

        Notification::make()
            ->title('NUEVO TICKET DE SOPORTE CREADO')
            ->body($isScrumBacklog
                ? 'Ticket N.º '.$ticket->getKey().' enviado al backlog Scrum de Becky Acosta para refinamiento.'
                : 'Ticket N.º '.$ticket->getKey().' registrado y asignado de forma exitosa.')
            ->icon('heroicon-m-ticket')
            ->iconColor('success')
            ->success()
            ->send();
    }
}
