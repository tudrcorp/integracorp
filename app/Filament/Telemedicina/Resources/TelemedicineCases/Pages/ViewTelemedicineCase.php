<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Pages;

use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use App\Models\TelemedicinePatient;
use Filament\Resources\Pages\ViewRecord;
use App\Models\TelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Detalle de Caso';

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
            Action::make('add_follow_up')
                ->label('Hacer Seguimiento')
                ->icon('healthicons-f-health-literacy')
                ->color('success')
                ->action(function () {
                    
                    $ownerRecord = $this->record;
                    
                    $case        = TelemedicineCase::where('code', $ownerRecord->code)->first();
                    $patient     = TelemedicinePatient::where('id', $ownerRecord->telemedicine_patient_id)->first();
                    $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $ownerRecord->telemedicine_patient_id)->exists();

                    session()->forget('case');
                    session()->forget('patient');
                    session()->forget('exit_record');

                    //Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                    session(['case' => $case]);
                    session(['patient' => $patient]);
                    session(['exit_record' => $exit_record]);

                    return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                })
                ->hidden(function () {
                    return $this->record->status == 'ALTA MEDICA';
                })
        ];
    }
}