<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Detalle de la Historia Clínica';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar Historia Clínica')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl()),
        ];
    }
}
