<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Pages;

use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Http\Controllers\NotificationController;
use App\Support\MassNotificationDispatchService;
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
                        ->helperText('Opcional! solo si es personalizada'),
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
                        ->helperText('Opcional! solo si es personalizada'),
                ])
                ->action(function ($record, $data) {
                    try {
                        $success = NotificationController::sendNotificationEmailSingle($record, $data);

                        $this->record->refresh();

                        Notification::make()
                            ->body($success
                                ? 'La notificación de prueba fue enviada exitosamente.'
                                : 'No se pudo enviar la prueba. Revisa los logs del sistema.')
                            ->{$success ? 'success' : 'danger'}()
                            ->send();
                    } catch (\Throwable $th) {
                        Log::error($th);
                    }
                }),

            Action::make('send_notification')
                ->label(fn ($record) => $record->isScheduledForFuture() ? 'Confirmar envío programado' : 'Envío Masivo')
                ->icon('heroicon-s-megaphone')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(fn ($record) => $record->isScheduledForFuture()
                    ? 'CONFIRMAR ENVÍO PROGRAMADO'
                    : 'ENVIAR NOTIFICACIÓN')
                ->modalDescription(fn ($record) => $record->isScheduledForFuture()
                    ? 'La notificación se enviará automáticamente el '.$record->date_programed->format('d/m/Y H:i').'. No se enviará ahora.'
                    : '¿Confirmas el envío inmediato a todos los destinatarios asociados?')
                ->modalSubmitActionLabel(fn ($record) => $record->isScheduledForFuture() ? 'Confirmar programación' : 'Enviar ahora')
                ->hidden(function ($record) {
                    return $record->status == 'POR-APROBAR';
                })
                ->action(function ($record) {
                    try {
                        $result = MassNotificationDispatchService::dispatch($record);

                        Notification::make()
                            ->body($result->message)
                            ->{$result->success ? 'success' : 'warning'}()
                            ->send();
                    } catch (\Throwable $th) {
                        Log::error($th);

                        Notification::make()
                            ->body('No se pudo encolar el envío masivo. Revisa los logs del sistema.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
