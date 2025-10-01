<?php

namespace App\Filament\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineHistoryPatients extends ListRecords
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Historia Clínica del Paciente';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()
            //     ->label('Crear Historia Clínica')
            //     ->icon('heroicon-s-plus')
        ];
    }
}