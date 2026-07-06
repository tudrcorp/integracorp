<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Filament\Business\Resources\Agencies\Concerns\QueuesAgencyFichaPdfEmail;
use App\Filament\Business\Resources\Agencies\Widgets\AgencyGeoChart;
use App\Filament\Business\Resources\Agencies\Widgets\AgentActiveForEstructureChart;
use App\Filament\Business\Resources\Agencies\Widgets\ControlActividadInteraccion;
use App\Filament\Business\Resources\Agencies\Widgets\NewRegisterAgencyForMountChart;
use App\Filament\Business\Resources\Agencies\Widgets\StatsOverviewAgency;
use App\Filament\Business\Resources\Agencies\Widgets\TotalEstructureAgency;
use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;
use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Models\AgencyNoteBlog;
use App\Support\GuiaChat\GuiaChatPublicUrl;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListAgencies extends ListRecords
{
    use ExposesTableToWidgets;
    use QueuesAgencyFichaPdfEmail;

    private const GUIA_CHAT_LINK_AUDIT_ROUTE = 'business.agencies.send-guia-chat-link';

    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Gestión de Agencias';

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
                ->label('Crear agencia')
                ->icon('heroicon-s-user-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('send_link')
                ->label('Enviar enlace de registro')
                ->icon('heroicon-m-paper-airplane')
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->modalHeading('Enviar enlace de registro externo')
                ->modalDescription('Si selecciona una agencia, el enlace incluye el código cifrado en la URL (Integracorp → /agency/c/…); si no, se enviará el enlace general. Indique al menos correo electrónico o WhatsApp para enviarlo.')
                ->modalIcon('heroicon-m-link')
                ->modalIconColor('success')
                ->modalSubmitActionLabel('Enviar')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Section::make('Agencia en el enlace')
                        ->description('El destinatario completará el registro bajo la estructura comercial de la agencia que elija; el código viaja cifrado igual que en el panel General.')
                        ->schema([
                            Select::make('agency_code')
                                ->label('Agencia')
                                ->searchable()
                                ->preload()
                                ->options(function (): array {
                                    $query = Agency::query()
                                        ->where('status', 'ACTIVO')
                                        ->orderBy('name_corporative');

                                    if (Auth::user()->is_accountManagers) {
                                        $query->where('ownerAccountManagers', Auth::id());
                                    }

                                    return $query
                                        ->get()
                                        ->mapWithKeys(fn (Agency $agency): array => [
                                            $agency->code => $agency->name_corporative.' · '.$agency->code,
                                        ])
                                        ->all();
                                })
                                ->default(fn () => Auth::user()?->code_agency)
                                ->placeholder('Seleccione una agencia')
                                ->helperText('Nombre corporativo y código que se asociarán al formulario de registro. Si lo dejas en blanco, se asociara InHOUSE.'),
                        ]),
                    Section::make('Destinatarios')
                        ->description('Opcional por campo: puede usar solo correo, solo WhatsApp o ambos en el mismo envío.')
                        ->schema([
                            Grid::make([
                                'default' => 1,
                                'lg' => 2,
                            ])->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255)
                                    ->autocomplete('email')
                                    ->prefixIcon('heroicon-m-envelope')
                                    ->placeholder('ejemplo@empresa.com')
                                    ->helperText('Correo válido donde recibirán el enlace.'),
                                TextInput::make('phone')
                                    ->label('WhatsApp')
                                    ->prefixIcon('heroicon-s-phone')
                                    ->tel()
                                    ->placeholder('04127018390 o +584121234567')
                                    ->helperText('Número con WhatsApp. Venezuela: 0412… sin espacios. Extranjero: código de país (+58…, +1…).'),
                            ]),
                        ]),
                ])
                ->action(function (array $data) {

                    try {
                        $agencyCode = $data['agency_code'] ?? null;
                        if (filled($agencyCode)) {
                            $allowedAgencyQuery = Agency::query()
                                ->where('status', 'ACTIVO')
                                ->where('code', $agencyCode);

                            if (Auth::user()->is_accountManagers) {
                                $allowedAgencyQuery->where('ownerAccountManagers', Auth::id());
                            }

                            if (! $allowedAgencyQuery->exists()) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                                    'reason' => 'invalid_or_unauthorized_agency_code',
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION')
                                    ->body('El código de agencia seleccionado no es válido o no tiene permisos para usarlo.')
                                    ->icon('heroicon-c-shield-exclamation')
                                    ->color('danger')
                                    ->send();

                                return false;
                            }
                        }

                        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
                        $link = blank($agencyCode)
                            ? $baseUrl.'/agency/c'
                            : $baseUrl.'/agency/c/'.Crypt::encryptString($agencyCode);

                        if ($data['phone'] == null && $data['email'] == null) {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                                'reason' => 'missing_email_and_phone',
                                'agency_code' => $agencyCode,
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return false;
                        }

                        if ($data['email'] != null) {

                            $sendEmail = NotificationController::send_email_agency_register($link, $data['email']);
                            if ($sendEmail == true) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_SENT', 'business.agencies.send-register-link', [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via email fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_FAILED', 'business.agencies.send-register-link', [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación via email NO fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }

                        if ($data['phone'] != null) {

                            $response = NotificationController::send_link_agency_register_wp($link, $data['phone']);
                            if ($response) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_SENT', 'business.agencies.send-register-link', [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via whatsapp fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_FAILED', 'business.agencies.send-register-link', [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación vía WhatsApp no pudo enviarse. Verifique el número e intente de nuevo.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }
                    } catch (\Throwable $th) {
                        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                            'error' => $th->getMessage(),
                            'recipient_email' => $data['email'] ?? null,
                            'recipient_phone' => $data['phone'] ?? null,
                            'agency_code' => $data['agency_code'] ?? null,
                        ]);

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
                ->modalDescription('Comparta el asistente virtual GUIA-CHAT con una agencia o prospecto. El destinatario recibirá el enlace público del chat guiado.')
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
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_GUIA_CHAT_LINK_SEND_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
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
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_GUIA_CHAT_LINK_WHATSAPP_SENT', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
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

                        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_GUIA_CHAT_LINK_WHATSAPP_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
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
                        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_GUIA_CHAT_LINK_SEND_FAILED', self::GUIA_CHAT_LINK_AUDIT_ROUTE, [
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

    /**
     * @return int|array<string, int|null>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewAgency::class,
            ControlActividadInteraccion::class,
            NewRegisterAgencyForMountChart::class,
            AgencyGeoChart::class,
            AgentActiveForEstructureChart::class,
            TotalEstructureAgency::class,
            TotalSaleForEstructureChart::class,
        ];
    }

    /**
     * Guarda una nota desde el slide-over del centro de acciones (sin cerrar el panel).
     */
    public function saveAgencyCommandCenterNoteFromSlideover(string $recordKey, string $note): void
    {
        try {
            if (! Schema::hasTable((new AgencyNoteBlog)->getTable())) {
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

            $base = Agency::query();
            if (Auth::user()?->is_accountManagers) {
                $base->where('ownerAccountManagers', Auth::id());
            }

            $agency = $base->whereKey($recordKey)->first();
            if ($agency === null) {
                Notification::make()
                    ->title('No autorizado o no encontrado')
                    ->body('No se pudo localizar la agencia o no tiene permisos para registrar la nota.')
                    ->danger()
                    ->send();

                return;
            }

            AgencyNoteBlog::create([
                'agency_id' => $agency->id,
                'note' => $note,
                'created_by' => Auth::user()->name ?? (string) Auth::id(),
            ]);

            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADDED', 'business.agencies.add-observation', [
                'agency_id' => $agency->id,
                'agency_code' => $agency->code,
                'note_length' => strlen($note),
                'source' => 'command_center_slideover',
            ]);

            Notification::make()
                ->title('Nota guardada con éxito')
                ->body('La nota se guardó correctamente y ya aparece en la bitácora.')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADD_FAILED', 'business.agencies.add-observation', [
                'agency_id' => $recordKey,
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
