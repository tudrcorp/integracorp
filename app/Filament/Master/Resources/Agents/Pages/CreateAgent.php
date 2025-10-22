<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\Master\Resources\Agents\AgentResource;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Formulario de Agente';

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            //4. creamos el usuario en la tabla users (AGENTES)
            $user = new User();
            $user->name = $record->name;
            $user->email = $record->email;
            $user->password = Hash::make('12345678');
            $user->is_agent = true;
            $user->code_agency = $record->code_agency;
            $user->code_agent = 'AGT-000' . $record->id;
            $user->link_agent = env('APP_URL') . '/agent/c/' . Crypt::encryptString($record->code);
            $user->agent_id = $record->id;
            $user->status = 'ACTIVO';
            $user->save();

            /**
             * Notificacion por correo electronico
             * CARTA DE BIENVENIDA
             * @param Agent $record
             */
            $record->sendCartaBienvenida($record->id, $record->name, $record->email);


            $phone = $record->phone;
            $email = $record->email;
            $nofitication = NotificationController::agent_activated($phone, $email, $record->agent_type_id == 2 ? config('parameters.PATH_AGENT') : config('parameters.PATH_SUBAGENT'));


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