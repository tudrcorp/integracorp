<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineHistoryPatients extends ListRecords
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Historias Clínicas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Historia Clínica')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
