<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

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

            if (Agency::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de Agencias de Corretaje. Por favor intenta con otro correo electrónico.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('NEGOCIOS: El Usuario ' . Auth::user()->name . ' intento registrar un agente con un correo electrónico ya existente en la tabla de agencias.');

                $this->halt();
            }

            if (User::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de Usuarios. Por favor intenta con otro correo electrónico.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('NEGOCIOS: El Usuario ' . Auth::user()->name . ' intento registrar un agente con un correo electrónico ya existente en la tabla de usuarios.');

                $this->halt();
            }

    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        
        try {
            //Si el usuario logueado es un administrador de cuentas
            if (Auth::user()->is_accountManagers) {
                //Actualizo el registro y le agrego el id del administrador de cuenta que realizo el registro
                $record->ownerAccountManagers = Auth::user()->id;
                $record->save();
            }

            //actualizo el owner code de la agencia si pertenece a nosotras
            $record->owner_code = $record->owner_code == null ? 'TDG-100' : $record->owner_code;
            $record->save();
            
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
}