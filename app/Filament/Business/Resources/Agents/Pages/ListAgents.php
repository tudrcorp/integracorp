<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Filament\Business\Resources\Agents\Widgets\ControlActividadInteraccion;
use App\Filament\Business\Resources\Agents\Widgets\NewRegisterAgentForMountChart;
use App\Filament\Business\Resources\Agents\Widgets\StatsOverviewAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalForStateAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleForAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendBusinessAgentFichaPdfMailJob;
use App\Models\Agent;
use App\Models\AgentNoteBlog;
use App\Support\AgentActivity\AgentActivityQuery;
use App\Support\BusinessAgentFichaPdfAccess;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListAgents extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Gestión de Agentes';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';


    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear agente')
                ->icon('heroicon-s-user-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('send_link')
                ->label('Enviar enlace de registro')
                ->icon('heroicon-m-paper-airplane')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->modalHeading('Envio de link para registro externo')
                ->modalIcon('heroicon-m-link')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Section::make()
                        ->description('El link puede sera enviado por email y/o telefono!')
                        ->schema([
                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->maxLength(255)
                                ->autocomplete('email')
                                ->prefixIcon('heroicon-m-envelope')
                                ->helperText('Use una dirección de correo institucional o personal válida.'),
                            TextInput::make('phone')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->helperText('El numero de telefono debe estar asociado a WhatSapp. El formato de ser 04127018390, 04146786543, 04246754321, sin espacios en blanco. Para los numeros extrangeros deben colocar el codigo de area, Ejemplo: +1987654567, +36909876578')
                                ->label('Número de teléfono'),
                        ]),
                ])
                ->action(function (array $data) {

                    try {

                        if ($data['phone'] == null && $data['email'] == null) {
                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return false;
                        }

                        if ($data['email'] != null) {

                            $link = config('parameters.register_agent');
                            $sendEmail = NotificationController::send_email_agent_register($link, $data['email']);
                            if ($sendEmail == true) {

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via email fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {

                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación via email NO fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }

                        if ($data['phone'] != null) {

                            $link = config('parameters.register_agent');
                            $response = NotificationController::send_link_agent_register_wp($link, $data['phone']);
                            if ($response) {
                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via whatsapp fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación via email NO fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }

                    } catch (\Throwable $th) {
                        Notification::make()
                            ->title('ENVIO FALLIDO')
                            ->body($th->getMessage())
                            ->icon('heroicon-c-shield-check')
                            ->color('danger')
                            ->send();
                    }
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewAgent::class,
            ControlActividadInteraccion::class,
            NewRegisterAgentForMountChart::class,
            TotalForStateAgent::class,
            TotalSaleMonthlyNowVsLastAgent::class,
            TotalSaleForAgent::class,
        ];
    }

    protected function getTableColumns(): array
    {
        return [

        ];
    }

    /**
     * Guarda una nota desde el slide-over del centro de acciones sin cerrar el panel (re-render de Livewire).
     */
    public function saveAgentCommandCenterNoteFromSlideover(string $recordKey, string $note): void
    {
        try {
            if (! Schema::hasTable((new AgentNoteBlog)->getTable())) {
                Notification::make()
                    ->title('No disponible')
                    ->body('El historial de notas no está disponible en esta base de datos.')
                    ->warning()
                    ->send();

                return;
            }

            $note = Str::limit(trim($note), 255, '');
            if ($note === '') {
                Notification::make()
                    ->title('Nota vacía')
                    ->body('Escriba el texto de la observación antes de guardar.')
                    ->warning()
                    ->send();

                return;
            }

            $base = Agent::query();
            if (! empty(Auth::user()?->is_accountManagers)) {
                $base->where('ownerAccountManagers', Auth::id());
            }

            $agent = AgentActivityQuery::applyToAgentsQuery($base)->whereKey($recordKey)->first();
            if ($agent === null) {
                Notification::make()
                    ->title('No autorizado o no encontrado')
                    ->body('No se pudo localizar el agente o no tiene permisos para registrar la nota.')
                    ->danger()
                    ->send();

                return;
            }

            AgentNoteBlog::create([
                'agent_id' => $agent->id,
                'note' => $note,
                'created_by' => Auth::user()->name ?? (string) Auth::id(),
            ]);

            SecurityAudit::log('AUDIT_BUSINESS_AGENT_OBSERVATION_ADDED', 'business.agents.add-observation', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'note_length' => strlen($note),
                'source' => 'command_center_slideover',
            ]);

            Notification::make()
                ->title('Nota guardada con éxito')
                ->body('La nota se guardó correctamente y ya aparece en la bitácora.')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENT_OBSERVATION_ADD_FAILED', 'business.agents.add-observation', [
                'agent_id' => $recordKey,
                'error' => $th->getMessage(),
                'source' => 'command_center_slideover',
            ]);

            Notification::make()
                ->title('No se pudo guardar la nota')
                ->body('Intente de nuevo o contacte a soporte si el problema continúa.')
                ->danger()
                ->send();
        }
    }

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
}
