<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;

class ViewTelemedicinePatient extends ViewRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    //title
    protected static ?string $title = 'Informacion principal del paciente';
    

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicinePatientResource::getUrl('index')),
        ];
    }
}