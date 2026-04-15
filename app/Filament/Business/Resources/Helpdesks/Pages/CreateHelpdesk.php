<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Concerns\PreparesHelpdeskColaboradorAssigneesOnCreate;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Services\HelpdeskTicketAssigneeMailService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateHelpdesk extends CreateRecord
{
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

    /**
     * Texto para WhatsApp (caption): sin HTML; salto de línea para legibilidad en el móvil.
     */
    private function buildWhatsAppBodyForAssignedTicket(HelpDesk $ticket): string
    {
        $tz = (string) config('app.timezone');
        $createdAt = $ticket->created_at !== null
            ? $ticket->created_at->timezone($tz)->format('d/m/Y H:i')
            : '—';
        $creator = filled($ticket->created_by) ? (string) $ticket->created_by : '—';
        $priorityRaw = strtoupper((string) $ticket->priority);
        $priorityLabel = match ($priorityRaw) {
            'BAJA' => 'Baja — puede esperar',
            'MEDIA' => 'Media — flujo normal',
            'ALTA' => 'Alta — bloquea trabajo',
            default => filled($ticket->priority) ? (string) $ticket->priority : '—',
        };
        $description = trim(html_entity_decode(strip_tags((string) $ticket->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($description) > 900) {
            $description = mb_substr($description, 0, 897).'...';
        }
        $ticketNo = (string) $ticket->getKey();

        return <<<TEXT
        Le han asignado un ticket de soporte en INTEGRACORP.

        Ticket N.º {$ticketNo}
        Creado por: {$creator}
        Fecha y hora: {$createdAt}
        *Prioridad: {$priorityLabel}*

        *{$description}*

        Debe conectarse al sistema INTEGRACORP con su usuario y contraseña para dar inicio a la gestión del ticket.
        TEXT;
    }

    private function sendNotificationToColaboradorWhatsApp(HelpDesk $record): bool
    {
        try {
            $ticket = HelpdeskTicketAssigneeMailService::loadTicketWithAssigneesForNotifications($record);

            if ($ticket->rrhhColaboradores->isEmpty()) {
                return true;
            }

            $body = $this->buildWhatsAppBodyForAssignedTicket($ticket);
            $userId = Auth::id();
            if ($userId === null) {
                return false;
            }

            foreach ($ticket->rrhhColaboradores as $colaborador) {
                $phone = $colaborador->telefonoCorporativo ?: $colaborador->telefono;
                $phone = is_string($phone) ? trim($phone) : '';
                if ($phone === '') {
                    Log::warning('Helpdesk: colaborador asignado sin teléfono; no se envía WhatsApp.', [
                        'help_desk_id' => $ticket->getKey(),
                        'rrhh_colaborador_id' => $colaborador->getKey(),
                    ]);

                    continue;
                }

                SendNotificacionWhatsApp::dispatch($userId, $body, $phone);
            }

            return true;
        } catch (Throwable $th) {
            Log::warning('Helpdesk: error al enviar notificación WhatsApp al crear ticket.', [
                'message' => $th->getMessage(),
                'help_desk_id' => $record->getKey(),
                'context' => 'WhatsApp a colaboradores asignados (misma lista que el correo).',
            ]);

            return false;
        }
    }

    protected function afterCreate(): void
    {
        HelpdeskTicketAssigneeMailService::sendToEachAssignee($this->getRecord());
        $this->sendNotificationToColaboradorWhatsApp($this->getRecord());

        $colaborador = RrhhColaborador::query()->where('user_id', Auth::id())->first();
        if ($colaborador !== null) {
            $ticket = $this->getRecord();
            $colaborador->sendNotification(
                title: 'NUEVO TICKET DE SOPORTE CREADO',
                body: 'Ticket N.º '.$ticket->getKey().' registrado. Conéctese a INTEGRACORP con su usuario y contraseña para dar seguimiento.',
                icon: 'heroicon-m-ticket',
                iconColor: 'success',
            );
        }
    }
}
