<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Filament\Master\Resources\Agencies\AgencyResource;
use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Agencias Generales Asociadas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->label('Registrar nueva agencia'),
            // Action::make('send_link')
            //     ->label('Enviar link')
            //     ->icon('heroicon-m-link')
            //     ->color('warning')
            //     ->modalHeading('Envio de link para registro externo')
            //     ->modalIcon('heroicon-m-link')
            //     ->modalWidth(Width::ExtraLarge)
            //     ->form([
            //         Section::make()
            //             ->description('El enlace se arma con el código de su agencia en sesión (code_agency), cifrado en la URL: dominio Integracorp + /agency/c/ + código encriptado. Puede enviarlo por correo y/o WhatsApp.')
            //             ->schema([
            //                 TextInput::make('email')
            //                     ->label('Correo Electrónico')
            //                     ->email()
            //                     ->maxLength(255)
            //                     ->autocomplete('email')
            //                     ->prefixIcon('heroicon-m-envelope')
            //                     ->helperText('Use una dirección de correo institucional o personal válida.'),
            //                 TextInput::make('phone')
            //                     ->prefixIcon('heroicon-s-phone')
            //                     ->tel()
            //                     ->helperText('El numero de telefono debe estar asociado a WhatSapp. El formato de ser 04127018390, 04146786543, 04246754321, sin espacios en blanco. Para los numeros extrangeros deben colocar el codigo de area, Ejemplo: +1987654567, +36909876578')
            //                     ->label('Número de teléfono'),
            //             ]),
            //     ])
            //     ->action(function (array $data) {
            //         try {
            //             $agencyCode = Auth::user()?->code_agency;
            //             if (blank($agencyCode)) {

            //                 SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
            //                     'reason' => 'missing_session_code_agency',
            //                 ]);

            //                 Notification::make()
            //                     ->title('NOTIFICACION')
            //                     ->body('Su usuario no tiene un code_agency asociado; no se puede generar el enlace de registro.')
            //                     ->icon('heroicon-c-shield-exclamation')
            //                     ->color('warning')
            //                     ->send();

            //                 return false;
            //             }

            //             if (! Agency::query()->where('code', $agencyCode)->where('status', 'ACTIVO')->exists()) {

            //                 SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
            //                     'reason' => 'invalid_or_inactive_agency_for_session_code',
            //                     'agency_code' => $agencyCode,
            //                 ]);

            //                 Notification::make()
            //                     ->title('NOTIFICACION')
            //                     ->body('No existe una agencia activa para el código de su sesión. Contacte a soporte.')
            //                     ->icon('heroicon-c-shield-exclamation')
            //                     ->color('danger')
            //                     ->send();

            //                 return false;
            //             }

            //             $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
            //             $link = $baseUrl.'/agency/c/'.Crypt::encryptString($agencyCode);

            //             if ($data['phone'] == null && $data['email'] == null) {
            //                 SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
            //                     'reason' => 'missing_email_and_phone',
            //                     'agency_code' => $agencyCode,
            //                 ]);

            //                 Notification::make()
            //                     ->title('NOTIFICACION')
            //                     ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
            //                     ->icon('heroicon-c-shield-exclamation')
            //                     ->color('warning')
            //                     ->send();

            //                 return false;
            //             }

            //             if ($data['email'] != null) {
            //                 $sendEmail = NotificationController::send_email_agency_register($link, $data['email']);
            //                 if ($sendEmail === true) {
            //                     SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_SENT', 'master.agencies.send-register-link', [
            //                         'recipient_email' => $data['email'],
            //                         'agency_code' => $agencyCode,
            //                     ]);

            //                     Notification::make()
            //                         ->title('NOTIFICACION ENVIADA')
            //                         ->body('La notificación via email fue enviada con exito.')
            //                         ->icon('heroicon-c-shield-check')
            //                         ->color('success')
            //                         ->send();
            //                 } else {
            //                     SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_FAILED', 'master.agencies.send-register-link', [
            //                         'recipient_email' => $data['email'],
            //                         'agency_code' => $agencyCode,
            //                     ]);

            //                     Notification::make()
            //                         ->title('ENVIO FALLIDO')
            //                         ->body('La notificación via email NO fue enviada con exito.')
            //                         ->icon('heroicon-c-shield-check')
            //                         ->color('danger')
            //                         ->send();
            //                 }
            //             }

            //             if ($data['phone'] != null) {
            //                 $response = NotificationController::send_link_agency_register_wp($link, $data['phone']);
            //                 if ($response) {
            //                     SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_SENT', 'master.agencies.send-register-link', [
            //                         'recipient_phone' => $data['phone'],
            //                         'agency_code' => $agencyCode,
            //                     ]);

            //                     Notification::make()
            //                         ->title('NOTIFICACION ENVIADA')
            //                         ->body('La notificación via whatsapp fue enviada con exito.')
            //                         ->icon('heroicon-c-shield-check')
            //                         ->color('success')
            //                         ->send();
            //                 } else {
            //                     SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_FAILED', 'master.agencies.send-register-link', [
            //                         'recipient_phone' => $data['phone'],
            //                         'agency_code' => $agencyCode,
            //                     ]);

            //                     Notification::make()
            //                         ->title('ENVIO FALLIDO')
            //                         ->body('La notificación vía WhatsApp no pudo enviarse. Verifique el número e intente de nuevo.')
            //                         ->icon('heroicon-c-shield-check')
            //                         ->color('danger')
            //                         ->send();
            //                 }
            //             }
            //         } catch (\Throwable $th) {
            //             SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
            //                 'error' => $th->getMessage(),
            //                 'recipient_email' => $data['email'] ?? null,
            //                 'recipient_phone' => $data['phone'] ?? null,
            //                 'agency_code' => Auth::user()?->code_agency,
            //             ]);

            //             Notification::make()
            //                 ->title('ENVIO FALLIDO')
            //                 ->body($th->getMessage())
            //                 ->icon('heroicon-c-shield-check')
            //                 ->color('danger')
            //                 ->send();
            //         }
            //     }),

            /**Version 2.0 */
            Action::make('send_link')
                ->label('Enviar link')
                ->icon('heroicon-m-link')
                ->color('warning')
                ->modalHeading('Envio de link para registro externo')
                ->modalIcon('heroicon-m-link')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Section::make()
                        ->description('Elija la agencia cuyo código viajará cifrado en la URL de registro (misma lógica que el panel General: dominio Integracorp + /agency/c/ + código encriptado). El enlace puede enviarse por correo y/o WhatsApp.')
                        ->schema([
                            Hidden::make('agency_code')->default(function (Get $get) {
                                return Auth::user()->code_agency;
                            }),
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
                        $agencyCode = $data['agency_code'] ?? null;
                        if (blank($agencyCode)) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
                                'reason' => 'missing_agency_code',
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('Debe seleccionar la agencia cuyo código se usará en el enlace de registro.')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return false;
                        }

                        $allowedAgencyQuery = Agency::query()
                            ->where('status', 'ACTIVO')
                            ->where('code', $agencyCode);

                        if (Auth::user()->is_accountManagers) {
                            $allowedAgencyQuery->where('ownerAccountManagers', Auth::id());
                        }

                        if (! $allowedAgencyQuery->exists()) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
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

                        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
                        $link = $baseUrl . '/agency/c/' . Crypt::encryptString($agencyCode);

                        if ($data['phone'] == null && $data['email'] == null) {
                            SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
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
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_SENT', 'master.agencies.send-register-link', [
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
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_EMAIL_FAILED', 'master.agencies.send-register-link', [
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
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_SENT', 'master.agencies.send-register-link', [
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
                                SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_WHATSAPP_FAILED', 'master.agencies.send-register-link', [
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
                        SecurityAudit::log('AUDIT_MASTER_AGENCY_REGISTER_LINK_SEND_FAILED', 'master.agencies.send-register-link', [
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
        ];
    }
}
