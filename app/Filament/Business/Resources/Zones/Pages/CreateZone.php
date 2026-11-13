<?php

namespace App\Filament\Business\Resources\Zones\Pages;

use App\Filament\Business\Resources\Zones\ZoneResource;
use App\Models\Zone;
use Filament\Resources\Pages\CreateRecord;

class CreateZone extends CreateRecord
{
    protected static string $resource = ZoneResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = (int) (Zone::query()->max('position') ?? 0) + 1;

        return $data;
    }
}
