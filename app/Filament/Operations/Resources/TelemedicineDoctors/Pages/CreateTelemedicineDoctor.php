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

    protected function afterCreate(): void
    {
        try {
            
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