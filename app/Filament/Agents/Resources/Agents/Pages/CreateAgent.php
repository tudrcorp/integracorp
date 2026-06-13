<?php

namespace App\Filament\Agents\Resources\Agents\Pages;

use App\Filament\Agents\Resources\Agents\AgentResource;
use App\Http\Controllers\NotificationController;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Subagente creado correctamente')
            ->body('El subagente ha sido creado correctamente')
            ->success()
            ->send();
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        try {
            $user = new User;
            $user->name = $record->name;
            $user->email = $record->email;
            $user->password = Hash::make('12345678');
            $user->is_agent = true;
            $user->agent_id = $record->id;
            $user->code_agent = 'AGT-000'.$record->id;
            $user->status = 'ACTIVO';
            $user->save();
        } catch (\Throwable) {
            Notification::make()
                ->title('Subagente creado, pero falló el usuario')
                ->body('No se pudo crear el usuario de acceso. Verifique que el correo no esté registrado e intente nuevamente con soporte.')
                ->danger()
                ->send();

            return;
        }

        try {
            $record->sendCartaBienvenida($record->id, $record->name, $record->email, '12345678');
        } catch (\Throwable) {
            Notification::make()
                ->title('Usuario creado, pero no se envió la carta de bienvenida')
                ->body('El subagente quedó registrado. La carta de bienvenida no pudo encolarse; contacte a soporte si el correo no llega.')
                ->warning()
                ->send();
        }

        $panelPath = config('parameters.PATH_AGENT');

        try {
            if (! filled($record->phone)) {
                throw new \RuntimeException('El subagente no tiene teléfono registrado.');
            }

            $whatsapp = NotificationController::agent_activated(
                $record->phone,
                $record->email,
                $panelPath,
            );

            if (! ($whatsapp['success'] ?? false)) {
                Notification::make()
                    ->title('WhatsApp no enviado')
                    ->body($whatsapp['message'] ?? 'No se pudo encolar la notificación de activación.')
                    ->warning()
                    ->send();
            }
        } catch (\Throwable) {
            Notification::make()
                ->title('Usuario creado, pero no se envió WhatsApp')
                ->body('No se pudo enviar el mensaje con usuario y clave. Verifique el teléfono del subagente.')
                ->warning()
                ->send();
        }
    }
}
