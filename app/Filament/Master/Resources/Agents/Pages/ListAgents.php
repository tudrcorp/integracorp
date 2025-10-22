<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\NotificationController;
use App\Filament\Master\Resources\Agents\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Agentes Asociados';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar nuevo agente')
                ->icon('heroicon-s-user-plus'),
            Action::make('send_link')
                ->label('Enviar link')
                ->icon('heroicon-m-link')
                ->modalWidth(Width::ExtraLarge)
                ->requiresConfirmation()
                ->form([
                    Section::make()
                        ->heading('Informacion')
                        ->description('El link puede sera enviado por email y/o telefono!')
                        ->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->email(),
                            TextInput::make('phone')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->label('Número de teléfono'),
                        ])->columns(2),
                ])
                ->action(function (array $data) {

                    try {
                        // dd($data);
                        $link = Auth::user()->link_agency;

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

                            $sendEmail  = NotificationController::send_email_agency_register($link, $data['email']);
                            if ($sendEmail == true) {
                                LogController::log(Auth::user()->id, 'ENVIAO DE LINK PARA REGISTRO DE AGENCIA', 'ListAgencies::getHeaderActions:action()', 'NOTIFICACION ENVIADA');
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

                            $sendWp = NotificationController::send_link_agency_register_wp($link, $data['phone']);
                            if ($sendWp) {
                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via whatsapp fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('Fallo el envió de la notificación via WhatsApp. Verifique el numero de teléfono y vuelva a intentarlo.')
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