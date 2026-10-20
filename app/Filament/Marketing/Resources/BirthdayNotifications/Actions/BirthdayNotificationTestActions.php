<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Actions;

use App\Models\BirthdayNotification;
use App\Support\BirthdayNotificationTestDelivery;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BirthdayNotificationTestActions
{
    public static function whatsapp(): Action
    {
        return Action::make('test_notification_wp')
            ->label('Test(Via WhatsApp)')
            ->icon('heroicon-c-device-phone-mobile')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('ENVIAR NOTIFICACIÓN DE PRUEBA')
            ->modalDescription('Se enviará una prueba de WhatsApp con el contenido de esta notificación de cumpleaños.')
            ->hidden(fn (BirthdayNotification $record): bool => $record->status === 'POR-APROBAR'
                || ! in_array('whatsapp', (array) $record->channels, true))
            ->form([
                TextInput::make('phone')
                    ->label('Número de Teléfono')
                    ->helperText('Debe agregar los códigos de área. Ejemplo: +56, +57, +58, +1, etc. Si es un número local: +58412, +58414, +58424, +58426, +58212, etc.')
                    ->tel()
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre y Apellido')
                    ->helperText('Opcional. Si lo dejas vacío se usará «Prueba».'),
            ])
            ->action(function (BirthdayNotification $record, array $data): void {
                try {
                    $success = BirthdayNotificationTestDelivery::sendWhatsApp($record, $data);

                    Notification::make()
                        ->body($success
                            ? 'La notificación de prueba por WhatsApp fue enviada exitosamente.'
                            : 'No se pudo enviar la prueba por WhatsApp. Revisa los logs del sistema.')
                        ->{$success ? 'success' : 'danger'}()
                        ->send();
                } catch (Throwable $exception) {
                    Log::error($exception);

                    Notification::make()
                        ->body('No se pudo enviar la prueba por WhatsApp. Revisa los logs del sistema.')
                        ->danger()
                        ->send();
                }
            });
    }

    public static function email(): Action
    {
        return Action::make('test_notification_email')
            ->label('Test(Via Email)')
            ->icon('heroicon-c-at-symbol')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('ENVIAR NOTIFICACIÓN DE PRUEBA')
            ->modalDescription('Se enviará una prueba de correo con el contenido de esta notificación de cumpleaños.')
            ->hidden(fn (BirthdayNotification $record): bool => $record->status === 'POR-APROBAR'
                || ! in_array('email', (array) $record->channels, true))
            ->form([
                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre y Apellido')
                    ->helperText('Opcional. Si lo dejas vacío se usará «Prueba».'),
            ])
            ->action(function (BirthdayNotification $record, array $data): void {
                try {
                    $success = BirthdayNotificationTestDelivery::sendEmail($record, $data);

                    Notification::make()
                        ->body($success
                            ? 'La notificación de prueba por correo fue enviada exitosamente.'
                            : 'No se pudo enviar la prueba por correo. Revisa los logs del sistema.')
                        ->{$success ? 'success' : 'danger'}()
                        ->send();
                } catch (Throwable $exception) {
                    Log::error($exception);

                    Notification::make()
                        ->body('No se pudo enviar la prueba por correo. Revisa los logs del sistema.')
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::whatsapp(),
            self::email(),
        ];
    }
}
