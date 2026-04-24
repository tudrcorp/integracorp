<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Filament\Master\Resources\Agents\AgentResource;
use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ListAgents extends ListRecords
{
    private const REGISTER_LINK_AUDIT_ROUTE = 'master.agents.send-register-link';

    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Estructura Comercial';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar nuevo agente')
                ->icon('heroicon-s-user-plus'),
            Action::make('send_link')
                ->label('Enviar link')
                ->icon('heroicon-m-link')
                ->color('warning')
                ->modalHeading('Envio de link para registro externo')
                ->modalIcon('heroicon-m-link')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Section::make()
                        ->description('El enlace se arma con el código de la agencia general en sesión (code_agency), cifrado en la URL: dominio Integracorp + /agency/c/ + código encriptado. Puede enviarlo por correo y/o WhatsApp.')
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
                ->action(function (array $data): void {
                    try {
                        $agencyCode = Auth::user()?->code_agency;
                        if (blank($agencyCode)) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'missing_session_code_agency',
                                'origin' => 'master.agents',
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('Su usuario no tiene un code_agency asociado; no se puede generar el enlace de registro.')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return;
                        }

                        if (! Agency::query()->where('code', $agencyCode)->where('status', 'ACTIVO')->exists()) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'invalid_or_inactive_agency_for_session_code',
                                'agency_code' => $agencyCode,
                                'origin' => 'master.agents',
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('No existe una agencia activa para el código de su sesión. Contacte a soporte.')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('danger')
                                ->send();

                            return;
                        }

                        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
                        $link = $baseUrl.'/agent/c/'.Crypt::encryptString($agencyCode);

                        if ($data['phone'] == null && $data['email'] == null) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                'reason' => 'missing_email_and_phone',
                                'agency_code' => $agencyCode,
                                'origin' => 'master.agents',
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return;
                        }

                        if ($data['email'] != null) {
                            $sendEmail = NotificationController::send_email_agency_register($link, $data['email']);
                            if ($sendEmail === true) {
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_SENT', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                    'origin' => 'master.agents',
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via email fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                    'origin' => 'master.agents',
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
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_SENT', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                    'origin' => 'master.agents',
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via whatsapp fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                    'origin' => 'master.agents',
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
                        SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', self::REGISTER_LINK_AUDIT_ROUTE, [
                            'error' => $th->getMessage(),
                            'recipient_email' => $data['email'] ?? null,
                            'recipient_phone' => $data['phone'] ?? null,
                            'agency_code' => Auth::user()?->code_agency,
                            'origin' => 'master.agents',
                        ]);

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
}
