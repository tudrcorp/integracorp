<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Pages;

use App\Filament\Resources\TelemedicineStudiesLists\TelemedicineStudiesListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineStudiesLists extends ListRecords
{
    protected static string $resource = TelemedicineStudiesListResource::class;

    protected static ?string $title = 'Lista de Estudios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}