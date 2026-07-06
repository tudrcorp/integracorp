<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Filament\Business\Resources\Agents\Concerns\QueuesAgentFichaPdfEmail;
use App\Filament\Business\Resources\Agents\Widgets\ControlActividadInteraccion;
use App\Filament\Business\Resources\Agents\Widgets\NewRegisterAgentForMountChart;
use App\Filament\Business\Resources\Agents\Widgets\StatsOverviewAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalForStateAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleForAgent;
use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;
use App\Http\Controllers\NotificationController;
use App\Models\Agent;
use App\Models\AgentNoteBlog;
use App\Support\AgentActivity\AgentActivityQuery;
use App\Support\GuiaChat\GuiaChatPublicUrl;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListAgents extends ListRecords
{
    use ExposesTableToWidgets;
    use QueuesAgentFichaPdfEmail;

    private const GUIA_CHAT_LINK_AUDIT_ROUTE = 'business.agents.send-guia-chat-link';

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
            Action::make('send_guia_chat_link')
                ->label('Enviar GUIA-CHAT')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ])
                ->modalHeading('Enviar enlace de GUIA-CHAT por WhatsApp')
                ->modalDescription('Comparta el asistente virtual GUIA-CHAT con un agente o prospecto. El destinatario recibirá el enlace público del chat guiado.')
                ->modalIcon('heroicon-m-chat-bubble-left-right')
                ->modalIconColor('info')
                ->modalSubmitActionLabel('Enviar por WhatsApp')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth(Width::TwoExtraLarge)
                ->form([
                    Section::make('Enlace GUIA-CHAT')
                        ->icon(Heroicon::Link)
                        ->description('URL pública del asistente virtual para registro guiado y soporte comercial.')
                        ->schema([
                            TextInput::make('guia_chat_url_preview')
                                ->label('Enlace a enviar')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn (): string => GuiaChatPublicUrl::url())
                                ->prefixIcon('heroicon-m-link')
                                ->helperText('Este enlace abre el chat en /chat/publico.'),
                        ]),
                    Section::make('Destinatario')
                        ->icon(Heroicon::Phone)
                        ->description('Indique el número de WhatsApp del destinatario.')
                        ->schema([
                            TextInput::make('phone')
                                ->label('WhatsApp')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->required()
                                ->placeholder('04127018390 o +584121234567')
                                ->helperText('Número con WhatsApp. Venezuela: 0412… sin espacios. Extranjero: código de país (+58…, +1…).'),
                        ]),
                ])
                ->action(function (array $data): void {
                    try {
                        $phone = trim((string) ($data['phone'] ?? ''));

                        if ($phone === '') {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENT_GUIA_CHAT_LINK_SEND_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
                                'reason' => 'missing_phone',
                            ]);

                            Notification::make()
                                ->title('Falta el destinatario')
                                ->body('Indique un número de WhatsApp para enviar el enlace de GUIA-CHAT.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->color('warning')
                                ->send();

                            return;
                        }

                        $link = GuiaChatPublicUrl::url();
                        $sent = NotificationController::send_guia_chat_link_wp($link, $phone);

                        if ($sent) {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENT_GUIA_CHAT_LINK_WHATSAPP_SENT', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
                                'recipient_phone' => $phone,
                                'guia_chat_url' => $link,
                            ]);

                            Notification::make()
                                ->title('WhatsApp enviado')
                                ->body('El enlace de GUIA-CHAT se envió por WhatsApp correctamente.')
                                ->icon('heroicon-m-check-circle')
                                ->color('success')
                                ->send();

                            return;
                        }

                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_GUIA_CHAT_LINK_WHATSAPP_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
                            'recipient_phone' => $phone,
                            'guia_chat_url' => $link,
                        ]);

                        Notification::make()
                            ->title('No se pudo enviar por WhatsApp')
                            ->body('Verifique el número (formato y que tenga WhatsApp) e intente de nuevo.')
                            ->icon('heroicon-m-x-circle')
                            ->color('danger')
                            ->send();
                    } catch (\Throwable $th) {
                        SecurityAudit::log('AUDIT_BUSINESS_AGENT_GUIA_CHAT_LINK_SEND_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
                            'error' => $th->getMessage(),
                            'recipient_phone' => $data['phone'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Error al enviar')
                            ->body($th->getMessage())
                            ->icon('heroicon-m-x-circle')
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
}
