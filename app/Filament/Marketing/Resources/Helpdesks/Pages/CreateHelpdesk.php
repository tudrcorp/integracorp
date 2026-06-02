<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Resources\Helpdesks\Pages;

use App\Filament\Concerns\AssertsHelpdeskTicketCreationAccess;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Filament\Concerns\PreparesHelpdeskTeamOnCreate;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    use AssertsHelpdeskTicketCreationAccess;
    use DispatchesHelpdeskCreateNotifications;
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;
    use PreparesHelpdeskTeamOnCreate;

    protected static string $resource = HelpdeskResource::class;

    protected static function helpdeskTicketCreationEnforcesQuota(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->prepareHelpdeskColaboradorAssigneesForCreate($data);

        return $this->prepareHelpdeskTeamForCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $ticket = $this->getRecord();

        $this->dispatchHelpdeskCreateNotifications($ticket, 'marketing');

        Notification::make()
            ->title('NUEVO TICKET DE SOPORTE CREADO')
            ->body('Ticket N.º '.$ticket->getKey().' registrado. Conéctese a INTEGRACORP con su usuario y contraseña para dar seguimiento.')
            ->icon('heroicon-m-ticket')
            ->iconColor('success')
            ->success()
            ->send();
    }
}
