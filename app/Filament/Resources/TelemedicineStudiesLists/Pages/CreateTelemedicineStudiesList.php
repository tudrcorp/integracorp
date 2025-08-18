<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Pages;

use App\Filament\Resources\TelemedicineStudiesLists\TelemedicineStudiesListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineStudiesList extends CreateRecord
{
    protected static string $resource = TelemedicineStudiesListResource::class;

    protected static ?string $title = 'Lista de Estudios';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}