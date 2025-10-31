<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use App\Models\TelemedicinePatient;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Redirect;
use App\Models\TelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class ViewTelemedicineConsultationPatient extends ViewRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected static ?string $title = 'Detalle de Seguimiento';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar')
                ->button()
                ->icon(
                    'heroicon-s-pencil'
                )
                ->color('primary')
                ->action(function () {
                    
                    $record = $this->record;

                    $case        = TelemedicineCase::where('id', $record->telemedicine_case_id)->first();
                    $patient     = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();

                    session()->forget('case');
                    session()->forget('patient');

                    //Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                    session(['case' => $case]);
                    session(['patient' => $patient]);

                    //Limpio las variables de session si existen
                    session()->forget('action');
                    session()->forget('status');

                    //Asigno las variables de session
                    session()->put('action', 'edit');
                    session()->put('status', $this->record->status);
                    
                    return Redirect::route('filament.telemedicina.resources.telemedicine-consultation-patients.edit', ['record' => $this->record->id]);
                    
                }),

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