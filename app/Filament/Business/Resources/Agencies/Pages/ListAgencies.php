<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\ListRecords;
use App\Http\Controllers\NotificationController;
use App\Filament\Business\Resources\Agencies\AgencyResource;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Gestión de Agencias';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear agencia')
                ->icon('heroicon-s-user-plus')
                ->color('success'),
            Action::make('send_link')
                ->label('Enviar link')
                ->icon('heroicon-m-link')
                ->color('warning')
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
                                ->label('Número de teléfono')
                        ])
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

                            $link = config('parameters.REGISTER_AGENCY');
                            $sendEmail  = NotificationController::send_email_agency_register($link, $data['email']);
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

                            $link = config('parameters.REGISTER_AGENCY');
                            $response = NotificationController::send_link_agency_register_wp($link, $data['phone']);
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
                })
                
        ];
    }
}