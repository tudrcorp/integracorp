<?php

namespace App\Filament\Resources\TelemedicineExamenLists\Pages;

use App\Filament\Resources\TelemedicineExamenLists\TelemedicineExamenListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineExamenLists extends ListRecords
{
    protected static string $resource = TelemedicineExamenListResource::class;

    protected static ?string $title = 'Lista de Exámenes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}