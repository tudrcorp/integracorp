<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\String\TruncateMode;

class CreateAccountManager extends CreateRecord
{
    protected static string $resource = AccountManagerResource::class;

    protected static ?string $title = 'Formulario para Creación de Account Manager';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        // dd($this->data);
        if(User::where('email', $this->data['email'])->exists()){

            Notification::make()
                ->title('ERROR')
                ->body('El correo electrónico ya se encuentra registrado en la tabla de Usuarios. Por favor intenta con otro correo electrónico.')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
                
            $this->halt();

        }
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\AccountManager $record */
        $record = $this->getRecord();

        try {   

            // 2. Crear el usuario con una estructura más limpia
            $user = User::create([
                'name'                => $record->full_name,
                'phone'               => $record->phone,
                'email'               => $record->email,
                'password'            => Hash::make(12345678),
                'departament'         => ['NEGOCIOS'],
                'is_accountManagers'  => true,
                'status'              => 'ACTIVO',
            ]);

            // 3. Vincular el ID del usuario al registro actual
            $record->update([
                'user_id' => $user->id
            ]);

            // 4. (Opcional) Aquí podrías disparar el Job de bienvenida optimizado previamente
            // SendCartaBienvenidaAgenteAgencia::dispatch($user->id, $user->name, $user->email);

            //code...
        } catch (\Throwable $th) {

            // Registrar el error técnico en logs para el desarrollador
            Log::error("NEGOCIOS-ACCOUNT-MANAGER: Error en afterCreate AccountManager ID: {$record->id}", [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            // Notificación amigable para el usuario de Filament
            Notification::make()
                ->title('Error en el proceso de registro')
                ->body($th->getCode() === 0 ? $th->getMessage() : 'Ocurrió un error inesperado al crear el acceso de usuario.')
                ->danger()
                ->persistent() // Mantiene la notificación hasta que el usuario la cierre
                ->send();

            // Opcional: Si quieres que el registro de Filament no se quede "guardado" si falla el usuario,
            // deberías mover esta lógica a un Hook 'beforeCreate' o manejar el borrado aquí.

        }
        
    }
}