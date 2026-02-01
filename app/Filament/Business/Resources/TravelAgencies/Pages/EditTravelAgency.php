<?php

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTravelAgency extends EditRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = "Editar Agencia de Viajes";

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
