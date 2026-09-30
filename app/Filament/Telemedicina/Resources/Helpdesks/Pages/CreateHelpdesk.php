<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks\Pages;

use App\Filament\Concerns\AssertsHelpdeskTicketCreationAccess;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Filament\Telemedicina\Resources\Helpdesks\HelpdeskResource;
use App\Support\HelpdeskFormSchema;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateHelpdesk extends CreateRecord
{
    use AssertsHelpdeskTicketCreationAccess;
    use DispatchesHelpdeskCreateNotifications;
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;

    public const PANEL = 'telemedicina';

    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Crear ticket para Operaciones';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareHelpdeskColaboradorAssigneesForCreate($data);
    }

    protected function beforeCreate(): void
    {
        $this->assertHelpdeskCreationAllowedOrHalt();
        $this->assertAssigneesBelongToOperationsOrHalt();
        $this->validatePendingHelpdeskColaboradorAssigneesOrHalt();
    }

    /**
     * El formulario ya filtra la lista, pero la regla se vuelve a comprobar en el servidor:
     * un doctor solo puede asignar (o copiar) a colaboradores del departamento de Operaciones.
     */
    protected function assertAssigneesBelongToOperationsOrHalt(): void
    {
        if ($this->helpdeskColaboradorIdsPendingValidation === []) {
            Notification::make()
                ->title('Falta el responsable')
                ->body('Asigne el ticket a al menos un colaborador del equipo de Operaciones.')
                ->danger()
                ->send();

            $this->halt();
        }

        $outside = HelpdeskFormSchema::colaboradorIdsOutsideDepartments(
            [...$this->helpdeskColaboradorIdsPendingValidation, ...$this->helpdeskCcColaboradorIdsPendingValidation],
            HelpdeskFormSchema::TELEMEDICINE_ASSIGNEE_DEPARTMENTS,
        );

        if ($outside === []) {
            return;
        }

        SecurityAudit::log('AUDIT_TELEMEDICINE_HELPDESK_ASSIGNEE_REJECTED', self::PANEL.'.helpdesks.create', [
            'user_id' => Auth::id(),
            'rejected_colaborador_ids' => $outside,
        ]);

        Notification::make()
            ->title('Responsable no permitido')
            ->body('Desde Telemedicina solo puede asignar el ticket a colaboradores del equipo de Operaciones.')
            ->danger()
            ->send();

        $this->halt();
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

        $this->dispatchHelpdeskCreateNotifications($ticket, self::PANEL);

        Notification::make()
            ->title('Ticket enviado a Operaciones')
            ->body('Ticket N.º '.$ticket->getKey().' registrado y asignado. Puede seguirlo en «Mis tickets».')
            ->icon('heroicon-m-ticket')
            ->iconColor('success')
            ->success()
            ->send();
    }
}
