<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies\Pages;

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTravelAgency extends CreateRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Crear Agencia de Viajes';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()?->name;
        $data['updated_by'] = Auth::user()?->name;

        return $data;
    }
}
