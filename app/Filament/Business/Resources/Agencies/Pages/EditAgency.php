<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Formularios de edición de agencias';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;

        return $data;
    }

}