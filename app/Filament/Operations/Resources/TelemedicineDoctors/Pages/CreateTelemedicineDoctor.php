<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Models\User;

class CreateTelemedicineDoctor extends CreateRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected static ?string $title = 'Formulario de Registro de Médicos';

    /**
     * 
     * Metodo que se ejecuta antes de crear un registro
     * Valida que el RIF y el correo electrónico no se encuentren registrados en la base de datos.
     * 
     * @return void
     */
    protected function beforeCreate(): void
    {
        
            $email = $this->data['email'];

            if (User::where('email', $email)->exists()) {
                // dd('El Correo electrónico ya se encuentra registrado en la tabla de usuarios.');
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de usuarios. Por favor intente con otro correo electrónico.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar un doctor con un correo electrónico ya existente en la tabla de usuarios.');

                $this->halt();
            }

    }


    protected function afterCreate(): void
    {
        try {

            $email = $this->data['email'];

            if (User::where('email', $email)->exists()) {
                // dd('El Correo electrónico ya se encuentra registrado en la tabla de usuarios.');
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de usuarios.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar un doctor con un correo electrónico ya existente.');

                $this->halt();
            }
            
            $record = $this->getRecord();

            //Creamos el usuario en la tabla de usuarios
            $user = User::query()->create([
                'doctor_id' => $record->id,
                'name' => $record->full_name,
                'email' => $record->email,
                'password' => Hash::make('12345678'),
                'departament' => ["TELEMEDICINA"],
                'status' => 'ACTIVO',
                'created_by' => Auth::user()->name,
                'updated_by' => Auth::user()->name
            ]);

            if($user) {

                Notification::make()
                    ->title('USUARIO CREADO CORRECTAMENTE')
                    ->body('El usuario se ha creado correctamente')
                    ->icon('heroicon-c-user-circle')
                    ->iconColor('success')
                    ->success()
                    ->send();
            }
            
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('ERROR AL CREAR EL USUARIO')
                ->body($th->getMessage())
                ->icon('heroicon-m-exclamation-triangle')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('ACCION EXITOSA!')
            ->body('El Doctor se ha creado correctamente.')
            ->icon('entypo-pin')
            ->iconColor('success')
            ->success()
            ->send();
    }
}