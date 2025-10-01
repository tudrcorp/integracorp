<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Redirect;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class ViewTelemedicineConsultationPatient extends ViewRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected static ?string $title = 'Detalle de Seguimiento';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('primary')
                ->url(function () {
                    return Redirect::back()->getTargetUrl();
                }),
        ];
    }
}