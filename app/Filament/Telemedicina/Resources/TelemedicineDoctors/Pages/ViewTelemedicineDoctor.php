<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;

class ViewTelemedicineDoctor extends ViewRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicineDoctorResource::getUrl('index')),
            EditAction::make(),
        ];
    }
}