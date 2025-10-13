<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Models\FamilyHistory;
use App\Models\SurgicalHistory;
use App\Models\TelemedicineCase;
use Filament\Actions\CreateAction;
use App\Models\PathologicalHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\GynecologicalHistory;
use Illuminate\Support\Facades\Auth;
use App\Models\NoPathologicalHistory;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Formulario de Historia Clinica';

    protected function afterCreate()
    {
        try {

            $record = $this->getRecord()->toArray();

            //...Creamos la data en la tabla historica
            if(isset($record['observations_personal']) && $record['observations_personal'] != null) {
                $familyHistory = new FamilyHistory();
                $familyHistory->telemedicine_history_patient_id = $record['id'];
                $familyHistory->telemedicine_patient_id = $record['telemedicine_patient_id'];
                $familyHistory->observations = $record['observations_personal'];
                $familyHistory->created_by = Auth::user()->name;
                $familyHistory->save();
            }

            if (isset($record['observations_pathological']) && $record['observations_pathological'] != null) {
                $pathologicalHistory = new PathologicalHistory();
                $pathologicalHistory->telemedicine_history_patient_id = $record['id'];
                $pathologicalHistory->telemedicine_patient_id = $record['telemedicine_patient_id'];
                $pathologicalHistory->observations = $record['observations_pathological'];
                $pathologicalHistory->created_by = Auth::user()->name;
                $pathologicalHistory->save();
                
            }

            if (isset($record['observations_not_pathological']) && $record['observations_not_pathological'] != null) {
                $notPathologicalHistory = new NoPathologicalHistory();
                $notPathologicalHistory->telemedicine_history_patient_id = $record['id'];
                $notPathologicalHistory->telemedicine_patient_id = $record['telemedicine_patient_id'];
                $notPathologicalHistory->observations = $record['observations_not_pathological'];
                $notPathologicalHistory->created_by = Auth::user()->name;
                $notPathologicalHistory->save();
            }

            if (isset($record['history_surgical']) && $record['history_surgical'] != null) {
                $surgicalHistory = new SurgicalHistory();
                $surgicalHistory->telemedicine_history_patient_id = $record['id'];
                $surgicalHistory->telemedicine_patient_id = $record['telemedicine_patient_id'];
                $surgicalHistory->observations = $record['observations_not_pathological'];
                $surgicalHistory->created_by = Auth::user()->name;
                $surgicalHistory->save();
            }

            if (isset($record['observations_ginecologica']) && $record['observations_ginecologica'] != null) {
                $gynecologicalHistory = new GynecologicalHistory();
                $gynecologicalHistory->telemedicine_history_patient_id = $record['id'];
                $gynecologicalHistory->telemedicine_patient_id = $record['telemedicine_patient_id'];
                $gynecologicalHistory->observations = $record['observations_not_pathological'];
                $gynecologicalHistory->created_by = Auth::user()->name;
                $gynecologicalHistory->save();
            }

            //code...
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    protected function getRedirectUrl(): string
    {
        try {
            
            // session()->put('case', TelemedicineCase::where('telemedicine_patient_id', $this->data['telemedicine_patient_id'])->first());

            //... Le digo a la varianle de sesion que maneja el formulario que lo muestre
            session()->put('redCode', false);

            return URL::route('filament.telemedicina.resources.telemedicine-consultation-patients.create', [
                'record' => $this->data['telemedicine_patient_id'],
            ]);
            
        } catch (\Exception $e) {
            
            Notification::make()
                ->title('Error al obtener el estado del formulario.')
                ->danger()
                ->send();
                
            // Manejar la excepción aquí, por ejemplo, registrándola o mostrando un mensaje de error
            Log::error('Error al obtener el estado del formulario: ' . $e->getMessage());
            return $this->getResource()::getUrl('index'); // Redirigir a la página de índice en caso de error
            
        }
    }
}