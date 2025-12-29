<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Pages;

use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendNotificationMasive;
use App\Jobs\SendNotificationMasiveEmail;
use App\Models\DataNotification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ViewMassNotification extends ViewRecord
{
    protected static string $resource = MassNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('change_status')
                ->label('Aprobar Notificación')
                ->icon('heroicon-o-power')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('APROBAR NOTIFICACIÓN')
                ->hidden(function ($record) {
                    return $record->status == 'APROBADA';
                })
                ->action(function ($record) {
                    $record->status = 'APROBADA';
                    $record->approved_by = Auth::user()->id;
                    $record->save();
                    Notification::make()
                        ->body('El estado de la notificación fue cambiado exitosamente.')
                        ->success()
                        ->send();
                }),

            Action::make('test_notification_wp')
                ->label('Test(Via WhatsApp)')
                ->icon('heroicon-c-device-phone-mobile')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('ENVIAR NOTIFICACIÓN')
                ->hidden(function ($record) {
                    return $record->status == 'POR-APROBAR';
                })
                ->form([
                    TextInput::make('phone')
                        ->label('Número de Teléfono')
                        ->helperText('Debe agregar los códigos de area. Ejemplo: +56, +57, +58, +1, etc. Si es un numero local: +58412, +58414, +58424, +58426, +58212, etc.')
                        ->tel()
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre y Apellido')
                        ->helperText('Opcional! solo si es personalizada')
                ])
                ->action(function ($record, $data) {

                    try {

                        NotificationController::sendNotificationWpSingle($record, $data);

                        Notification::make()
                            ->body('La notificación fue enviada exitosamente.')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        Log::error($th);
                    }
                }),

            Action::make('test_notification_email')
                ->label('Test(Via Email)')
                ->icon('heroicon-c-at-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('ENVIAR NOTIFICACIÓN')
                ->hidden(function ($record) {
                    return $record->status == 'POR-APROBAR';
                })
                ->form([
                    TextInput::make('email')
                        ->label('Correo Electrónico')
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre y Apellido')
                        ->helperText('Opcional! solo si es personalizada')
                ])
                ->action(function ($record, $data) {

                    try {

                        NotificationController::sendNotificationEmailSingle($record, $data);

                        Notification::make()
                            ->body('La notificación fue enviada exitosamente.')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        Log::error($th);
                    }
                }),


            Action::make('send_notification')
                ->label('Envío Masivo')
                ->icon('heroicon-s-megaphone')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('ENVIAR NOTIFICACIÓN')
                ->hidden(function ($record) {
                    return $record->status == 'POR-APROBAR';
                })
                ->action(function ($record) {

                    try {
                        $recordID = $record->id;
                        $array_channels = $record->channels;

                        $infoNotificacionArray = $record->toArray();

                        for ($i = 0; $i < count($array_channels); $i++) {
                            if ($array_channels[$i] == 'whatsapp') {
                                $dataNotificationArray = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();
                                for ($j = 0; $j < count($dataNotificationArray); $j++) {
                                    SendNotificationMasive::dispatch($dataNotificationArray[$j], $infoNotificacionArray)->onQueue('system');
                                }
                            }
                            if ($array_channels[$i] == 'email') {

                                $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();
                                for ($j = 0; $j < count($array); $j++) {
                                    SendNotificationMasiveEmail::dispatch($array[$j]['email'], $record)->onQueue('system');
                                }
                            }
                        }

                        Notification::make()
                            ->body('La notificación fue enviada exitosamente. Integracorp le notificara cuando el proceso finalice.')
                            ->success()
                            ->send();
                            
                    } catch (\Throwable $th) {
                        Log::error($th);
                    }
                }),
        ];
    }
}