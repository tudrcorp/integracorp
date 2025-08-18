<?php

namespace App\Filament\Resources\TelemedicineExamenLists\Pages;

use App\Filament\Resources\TelemedicineExamenLists\TelemedicineExamenListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineExamenList extends CreateRecord
{
    protected static string $resource = TelemedicineExamenListResource::class;

    protected static ?string $title = 'Lista de Exámenes';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}