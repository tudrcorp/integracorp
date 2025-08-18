<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicinePatients extends ListRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()
            //     ->label('Crear paciente')
            //     ->icon('heroicon-s-plus')
            //     ->color('primary'),
        ];
    }
}