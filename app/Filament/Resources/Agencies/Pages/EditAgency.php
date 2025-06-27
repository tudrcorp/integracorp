<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Agencies\AgencyResource;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'EDITAR AGENCIA';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function AfterSave()
    {
        if ($this->record->agency_type_id == 1) {
            $this->record->update([
                'owner_code' => 'TDG-100',
            ]);
        }

        /**
         * Actualizo el usuario de la agencia
         * para que pueda acceder al portal 
         * de las agencias tipo master
         */
        User::select('id', 'code_agency', 'agency_type')
            ->where('code_agency', $this->record->code)
            ->update([
                'agency_type' => 'MASTER'
            ]);
    }
}