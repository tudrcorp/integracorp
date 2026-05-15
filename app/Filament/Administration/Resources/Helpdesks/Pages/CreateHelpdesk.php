<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Helpdesks\Pages;

use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Concerns\LabelsHelpdeskCreateAnotherFormAction;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Services\HelpdeskTicketAssigneeMailService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CreateHelpdesk extends CreateRecord
{
    use LabelsHelpdeskCreateAnotherFormAction;
    use PreparesHelpdeskColaboradorAssigneesOnCreate;

    protected static string $resource = HelpdeskResource::class;

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
        $panel = 'administration';

        try {
            $emailReport = HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport($ticket, $panel);
            $whatsAppReport = HelpdeskTicketAssigneeWhatsAppService::dispatchToEachAssigneeWithReport($ticket, Auth::id(), $panel);

            SecurityAudit::log('AUDIT_HELPDESK_TICKET_NOTIFICATIONS_PROCESSED', $panel.'.helpdesks.notifications', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'email_report' => [
                    'total_assignees' => $emailReport['total_assignees'],
                    'attempted' => $emailReport['attempted'],
                    'sent' => $emailReport['sent'],
                    'failed' => $emailReport['failed'],
                    'skipped_no_email' => $emailReport['skipped_no_email'],
                    'failures' => array_slice($emailReport['failures'], 0, 10),
                ],
                'whatsapp_report' => [
                    'total_assignees' => $whatsAppReport['total_assignees'],
                    'attempted' => $whatsAppReport['attempted'],
                    'dispatched' => $whatsAppReport['dispatched'],
                    'failed' => $whatsAppReport['failed'],
                    'skipped_no_phone' => $whatsAppReport['skipped_no_phone'],
                    'failures' => array_slice($whatsAppReport['failures'], 0, 10),
                ],
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_TICKET_CREATED', $panel.'.helpdesks.create', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'created_by' => $ticket->created_by,
                'status' => $ticket->status,
                'mail_sent_count' => $emailReport['sent'],
                'mail_failed_count' => $emailReport['failed'],
                'whatsapp_dispatched_count' => $whatsAppReport['dispatched'],
                'whatsapp_failed_count' => $whatsAppReport['failed'],
            ]);
        } catch (Throwable $th) {
            SecurityAudit::log('AUDIT_HELPDESK_TICKET_CREATE_FAILED', $panel.'.helpdesks.create', [
                'panel' => $panel,
                'helpdesk_id' => $ticket->getKey(),
                'created_by' => $ticket->created_by,
                'error' => $th->getMessage(),
                'exception' => $th::class,
                'where' => 'CreateHelpdesk::afterCreate',
            ]);
        }

        Notification::make()
            ->title('NUEVO TICKET DE SOPORTE CREADO')
            ->body('Ticket N.º '.$ticket->getKey().' registrado. Conéctese a INTEGRACORP con su usuario y contraseña para dar seguimiento.')
            ->icon('heroicon-m-ticket')
            ->iconColor('success')
            ->success()
            ->send();
    }
}
