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
        try {
            $ci = $this->data['ci'];
            $rif = $this->data['rif'];
            $email = $this->data['email'];

            if (Agent::where('ci', $ci)->exists() || Agent::where('rif', $rif)->exists()) {
            Notification::make()
                ->title('ERROR')
                ->body('El RIF o la CI ya se encuentra registrado en la tabla de agentes.')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();

            Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar un agente con un RIF ya existente.');

            $this->halt();
            }

            if (Agent::where('email', $email)->exists()) {
                Notification::make()
                    ->title('ERROR')
                    ->body('El Correo electrónico ya se encuentra registrado en la tabla de agencias.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar una agencia con un correo electrónico ya existente.');

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

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar una agencia con un correo electrónico ya existente.');

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

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar una agencia con un correo electrónico ya existente.');

                $this->halt();
            }
        } catch (\Throwable $th) {
            Log::error('Error al registrar un agente: ' . $th->getMessage());
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
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