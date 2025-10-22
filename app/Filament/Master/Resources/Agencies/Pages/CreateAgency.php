<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\Master\Resources\Agencies\AgencyResource;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Formulario de Agencia';  

    protected function afterCreate(): void
    {
        try {
            
            $record = $this->getRecord();

            //4. creamos el usuario en la tabla users
            $user = new User();
            $user->name = $record->name_corporative;
            $user->email = $record->email;
            $user->password = Hash::make('12345678');
            $user->is_agency = true;
            $user->code_agency = $record->code;
            $user->agency_type = 'GENERAL';
            $user->link_agency = env('APP_URL') . '/agency/c/' . Crypt::encryptString($record->code);
            $user->status = 'ACTIVO';
            $user->save();

            /**
             * Notificacion por whatsapp
             * @param Agency $record
             */
            $phone = $record->phone;
            $email = $record->email;
            $nofitication = NotificationController::agency_activated($record->code, $phone, $email, $record->agency_type_id == 1 ? config('parameters.PATH_MASTER') : config('parameters.PATH_GENERAL'));

            /**
             * Notificacion por correo electronico
             * CARTA DE BIENVENIDA
             * @param Agency $record
             */
            $record->sendCartaBienvenida($record->code, $record->name, $record->email);

            
        } catch (\Throwable $th) {

            Notification::make()
                ->title('EXCEPCION')
                ->body('Falla al realizar la activacion. Por favor comuniquese con el administrador.')
                ->icon('heroicon-s-x-circle')
                ->iconColor('error')
                ->color('error')
                ->send();
        }
    }
}