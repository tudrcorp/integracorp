<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\TelemedicineFollowUp;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Models\TelemedicinePatientMedications;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected static ?string $title = 'Formulario de Gestión de Servicio';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_history')
                ->label('Ver Historia Clinica')
                ->button()
                ->icon('healthicons-f-health-worker-form')
                ->color('primary')
                ->action(function () {
                    if (session()->get('patient')->telemedicinePatientHistory()->exists()) {

                        $patient = session()->get('patient');
                        $history = $patient->telemedicinePatientHistory()->first()->id;
                        return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.view', ['record' => $history]);
                        
                    } else {

                        Notification::make()
                            ->title('Paciente sin historia clínica, por favor llene la historia clínica del paciente')
                            ->warning()
                            ->color('warning')
                            ->send();
                        return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.create', ['record' => request()->query('id')]);
                        
                    }
                }),
            Action::make('back_dashboard')
                ->label('Dashboard')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('success')
                ->url(route('filament.telemedicina.pages.dashboard')),
            
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        isset($data['medications']) ? session()->put('medications', $data['medications']) : null;

        return $data;
    }

    /**
     * Creamos el registro de los medicamentos
     * asignados por el medico en la consulta
     * 
     * @return void
     * @author TuDrEnCasa
     * @since 1.0
     * @version 1.0
     * 
     * @param array $data, array $medications
     * 
     */
    protected function afterCreate()
    {
        try {

            $record = $this->getRecord()->toArray();

            $array = session()->get('medications');

            //...Si no hay medicamentos asignados, no hacemos nada
            if (empty($array)) {
                
                //...actualizo el estado del paciente
                $case = TelemedicineCase::find($record['telemedicine_case_id']);
                $case->status = 'EN SEGUIMIENTO';
                $case->save();
                return;
            }

            for ($i = 0; $i < count($array); $i++) {
                // dd($medications[1]['indications']);
                $medications = new TelemedicinePatientMedications();
                $medications->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                $medications->telemedicine_case_id                  = $record['telemedicine_case_id'];
                $medications->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                $medications->telemedicine_consultation_patient_id  = $record['id'];
                $medications->medicine                              = $array[$i]['medicines'];
                $medications->indications                           = $array[$i]['indications'];
                $medications->save();
            }

            //...Limpio la variable de sesion
            session()->forget('medications');

            //creamos el primer seguimiento
            $followUp = new TelemedicineFollowUp();
            //telemedicine_case_code
            $followUp->code                                 = $record['telemedicine_case_code'];
            $followUp->telemedicine_patient_id              = $record['telemedicine_patient_id'];
            $followUp->telemedicine_case_id                 = $record['telemedicine_case_id'];
            $followUp->telemedicine_doctor_id               = $record['telemedicine_doctor_id'];
            $followUp->telemedicine_consultation_patient_id = $record['id'];
            //Agregamos 1 dia a la fecha
            $followUp->next_follow_up = now()->addDay()->format('d/m/Y');
            $followUp->created_by = 'INTEGRACORP';
            $followUp->save();

            //actualizo el estado del paciente
            $case = TelemedicineCase::find($record['telemedicine_case_id']);
            $case->status = 'EN SEGUIMIENTO';
            $case->save();
            
            //code...
        } catch (\Throwable $th) {
            dd($th);
        }
        

    }

    public function getRedirectUrl(): string
    {
        //redirect to dashboard
        return URL::route('filament.telemedicina.pages.dashboard');
    }

    
}