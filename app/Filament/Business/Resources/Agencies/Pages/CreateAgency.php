<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    /**
     * 
     * Metodo que se ejecuta antes de crear un registro
     * Valida que el RIF y el correo electrónico no se encuentren registrados en la base de datos.
     * 
     * @return void
     */
    protected function beforeCreate(): void
    {

            $rif = $this->data['rif'];
            $email = $this->data['email'];

            if (Agency::where('rif', $rif)->exists()) {
                Notification::make()
                ->title('ERROR')
                ->body('El RIF ya se encuentra registrado en la tabla de agencias.')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
            
                Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un RIF ya existente.');
                
                $this->halt();
            }   

            if (Agency::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de agencias.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();
                
                Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un correo electrónico ya existente.');

                $this->halt();
            }

            if (Agent::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de agentes.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();
                
                Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un correo electrónico ya existente.');

                $this->halt();
            }

            if (User::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de usuarios.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();
                
                Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un correo electrónico ya existente.');

                $this->halt();
            }
            
    }

    protected function afterCreate(): void
    {
        try {

            //Si el usuario logueado es un administrador de cuentas
            if(Auth::user()->is_accountManagers) {
                //Actualizo el registro y le agrego el id del administrador de cuenta que realizo el registro
                $record = $this->getRecord();
                $record->ownerAccountManagers = Auth::user()->id;
                $record->save();
            }
            
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