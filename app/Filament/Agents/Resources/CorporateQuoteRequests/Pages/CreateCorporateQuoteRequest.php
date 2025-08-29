<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class CreateCorporateQuoteRequest extends CreateRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Cotización Corporativa DRESS-TAYLOR';

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            /**
             * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
             * ----------------------------------------------------------------------------------------------------
             * $record [Data de la cotizacion guardada en la base de dastos]
             */
            $recipient = User::where('is_admin', 1)->where('departament', 'COTIZACIONES')->get();
            foreach ($recipient as $user) {
                $recipient_for_user = User::find($user->id);
                Notification::make()
                    ->title('NUEVA SOLICITUD')
                    ->body('Se ha registrado una nueva solicitud de forma exitosa. Código: ' . $record->code)
                    ->icon('heroicon-m-tag')
                    ->iconColor('success')
                    ->success()
                    ->actions([
                        Action::make('view')
                            ->label('Ver solicitud')
                            ->button()
                            ->color('primary')
                            ->url(CorporateQuoteRequestResource::getUrl('view', ['record' => $record->id], panel: 'admin')),
                    ])
                    ->sendToDatabase($recipient_for_user);
            }

            //Notificacion por whatsapp al telefono de cotizaciones
            $sendNotificationWp = NotificationController::createdRequestDressTaylor($record->code, Auth::user()->name, $record->observations);
            
        } catch (\Throwable $th) {
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }

    //getCreatedNotification
    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('NOTIFICACIÓN')
            ->body('Solicitud de cotización corporativa creada de forma exitosa!.✅')
            ->icon('entypo-pin')
            ->iconColor('danger')
            ->success()
            ->persistent()
            ->send();
    }
}