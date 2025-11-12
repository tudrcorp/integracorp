<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\Agents\AgentResource;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

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