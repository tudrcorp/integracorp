<?php

namespace App\Filament\Resources\TelemedicineDoctors\Pages;

use App\Filament\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineDoctors extends ListRecords
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
