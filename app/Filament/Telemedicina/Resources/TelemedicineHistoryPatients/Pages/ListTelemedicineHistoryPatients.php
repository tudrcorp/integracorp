<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineHistoryPatients extends ListRecords
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
