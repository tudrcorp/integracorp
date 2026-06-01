<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Filament\Concerns\PreparesHelpdeskTeamOnCreate;
use App\Support\HelpdeskBusinessTicketCreationGate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    use DispatchesHelpdeskCreateNotifications;
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;
    use PreparesHelpdeskTeamOnCreate;

    protected static string $resource = HelpdeskResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return HelpdeskResource::canCreate();
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Crear otro')
            ->authorize(fn (): bool => HelpdeskResource::canCreate())
            ->visible(fn (): bool => HelpdeskResource::canCreate());
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

    public function mount(): void
    {
        parent::mount();

        $this->assertBusinessHelpdeskCreationAllowedOrRedirect();
    }

    protected function beforeCreate(): void
    {
        $this->assertBusinessHelpdeskCreationAllowedOrHalt();
        $this->validatePendingHelpdeskColaboradorAssigneesOrHalt();
    }

    protected function assertBusinessHelpdeskCreationAllowedOrRedirect(): void
    {
        $verdict = HelpdeskBusinessTicketCreationGate::allowsCreation();

        if ($verdict->allowed) {
            return;
        }

        Notification::make()
            ->title('No puede crear tickets')
            ->body($verdict->message)
            ->icon('heroicon-m-no-symbol')
            ->iconColor('danger')
            ->danger()
            ->persistent()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }

    protected function assertBusinessHelpdeskCreationAllowedOrHalt(): void
    {
        $verdict = HelpdeskBusinessTicketCreationGate::allowsCreation();

        if ($verdict->allowed) {
            return;
        }

        Notification::make()
            ->title('No puede crear tickets')
            ->body($verdict->message)
            ->icon('heroicon-m-no-symbol')
            ->iconColor('danger')
            ->danger()
            ->send();

        $this->halt();
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
