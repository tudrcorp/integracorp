<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineConsultationPatients extends ListRecords
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()
            //     ->label('Hacer consulta')
            //     ->icon('heroicon-s-plus')
            //     ->color('primary'),
        ];
    }
}