<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\Agencies\AgencyResource;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

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