<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Filament\Concerns\PreparesHelpdeskTeamOnCreate;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    use DispatchesHelpdeskCreateNotifications;
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;
    use PreparesHelpdeskTeamOnCreate;

    protected static string $resource = HelpdeskResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->prepareHelpdeskColaboradorAssigneesForCreate($data);

        return $this->prepareHelpdeskTeamForCreate($data);
    }

    protected function beforeCreate(): void
    {
        $this->validatePendingHelpdeskColaboradorAssigneesOrHalt();
    }

    /**
     * Filament intenta redirigir a la ruta `view` tras crear; en este recurso esa ruta no queda
     * registrada en el router (solo index, create y edit). Igual que al editar, volvemos al listado.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $ticket = $this->getRecord();

        $this->dispatchHelpdeskCreateNotifications($ticket, 'business');

        Notification::make()
            ->title('NUEVO TICKET DE SOPORTE CREADO')
            ->body('Ticket N.º '.$ticket->getKey().' registrado y asignado de forma exitosa.')
            ->icon('heroicon-m-ticket')
            ->iconColor('success')
            ->success()
            ->send();
    }
}
