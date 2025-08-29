<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineConsultationPatients extends ListRecords
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
