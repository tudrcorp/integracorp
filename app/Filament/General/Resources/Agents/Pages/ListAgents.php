<?php

namespace App\Filament\General\Resources\Agents\Pages;

use App\Filament\General\Resources\Agents\AgentResource;
use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ListAgents extends ListRecords
{
    private const REGISTER_LINK_AUDIT_ROUTE = 'general.agents.send-register-link';

    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Estructura Comercial';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar nuevo agente')
                ->icon('heroicon-s-user-plus')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('send_link')
                ->label('Enviar enlace de registro')
                ->icon('heroicon-m-paper-airplane')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ])
                ->modalHeading('Enviar enlace de registro de agentes')
                ->modalDescription('El enlace usa el código de agencia de su sesión (code_agency), cifrado en la URL (Integracorp → /agent/c/…). Indique al menos correo electrónico o WhatsApp.')
                ->modalIcon('heroicon-m-link')
                ->modalIconColor('primary')
                ->modalSubmitActionLabel('Enviar')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth(Width::TwoExtraLarge)
                ->form([
                    Section::make('Agencia en tu sesión')
                        ->icon(Heroicon::BuildingOffice2)
                        ->description('El futuro agente quedará asociado a esta estructura; no puede cambiarse desde este formulario.')
                        ->schema([
                            TextInput::make('session_agency_preview')
                                ->label('Código de Agencia')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn (): string => Auth::user()?->code_agency ?? '')
                                ->placeholder('Sin código asignado')
                                ->prefixIcon('heroicon-m-identification')
                                ->helperText('Si no hay código o la agencia no está activa, favor de contactar a soporte.'),
                        ]),
                    Section::make('Destinatarios')
                        ->icon(Heroicon::Users)
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
                ->action(function (array $data): void {
                    try {
                        $agencyCode = Auth::user()?->code_agency;
                        if (blank($agencyCode)) {
                            SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'missing_session_code_agency',
                            ]);

                            Notification::make()
                                ->title('Sin código de agencia')
                                ->body('Su usuario no tiene code_agency asociado; no se puede generar el enlace de registro.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->color('warning')
                                ->send();

                            return;
                        }

                        if (! Agency::query()->where('code', $agencyCode)->where('status', 'ACTIVO')->exists()) {
                            SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'invalid_or_inactive_agency_for_session_code',
                                'agency_code' => $agencyCode,
                            ]);

                            Notification::make()
                                ->title('Agencia no disponible')
                                ->body('No existe una agencia activa para el código de su sesión. Contacte a soporte.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->color('danger')
                                ->send();

                            return;
                        }

                        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
                        $link = $baseUrl.'/agent/c/'.Crypt::encryptString($agencyCode);

                        if ($data['phone'] == null && $data['email'] == null) {
                            SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'missing_email_and_phone',
                                'agency_code' => $agencyCode,
                            ]);

                            Notification::make()
                                ->title('Falta el destinatario')
                                ->body('Indique al menos un correo electrónico o un número de WhatsApp.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->color('warning')
                                ->send();

                            return;
                        }

                        if ($data['email'] != null) {
                            $sendEmail = NotificationController::send_email_agency_register($link, $data['email']);
                            if ($sendEmail === true) {
                                SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_EMAIL_SENT', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('Correo enviado')
                                    ->body('El enlace se envió por correo correctamente.')
                                    ->icon('heroicon-m-check-circle')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_EMAIL_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('No se pudo enviar el correo')
                                    ->body('Revise la dirección e intente de nuevo. Si el problema continúa, contacte a soporte.')
                                    ->icon('heroicon-m-x-circle')
                                    ->color('danger')
                                    ->send();
                            }
                        }

                        if ($data['phone'] != null) {
                            $response = NotificationController::send_link_agency_register_wp($link, $data['phone']);
                            if ($response) {
                                SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_WHATSAPP_SENT', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('WhatsApp enviado')
                                    ->body('El enlace se envió por WhatsApp correctamente.')
                                    ->icon('heroicon-m-check-circle')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_WHATSAPP_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('No se pudo enviar por WhatsApp')
                                    ->body('Verifique el número (formato y que tenga WhatsApp) e intente de nuevo.')
                                    ->icon('heroicon-m-x-circle')
                                    ->color('danger')
                                    ->send();
                            }
                        }
                    } catch (\Throwable $th) {
                        SecurityAudit::log('AUDIT_GENERAL_AGENT_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                            'error' => $th->getMessage(),
                            'recipient_email' => $data['email'] ?? null,
                            'recipient_phone' => $data['phone'] ?? null,
                            'agency_code' => Auth::user()?->code_agency,
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
}
