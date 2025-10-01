<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\TelemedicineFollowUp;
use Illuminate\Support\Facades\Auth;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientStudy;
use Filament\Notifications\Notification;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use Filament\Resources\Pages\CreateRecord;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientMedications;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Models\TelemedicineConsultationPatient;

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

        if (isset($data['feedbackOne']) && $data['feedbackOne'] == true) {
            session()->put('feedbackOne', $data['feedbackOne']);
        }
        //...Asignamos los valores a la variable de sesion
        //Medicamentos
        isset($data['medications']) ? session()->put('medications', $data['medications']) : null;

        //Laboratorios
        isset($data['labs']) ? session()->put('labs', $data['labs']) : null;
        isset($data['other_labs']) ? session()->put('other_labs', $data['other_labs']) : null;

        //Estudios
        isset($data['studies']) ? session()->put('studies', $data['studies']) : null;
        isset($data['other_studies']) ? session()->put('other_studies', $data['other_studies']) : null;

        //Consultas con especialistas
        isset($data['consult_specialist']) ? session()->put('consult_specialist', $data['consult_specialist']) : null;
        isset($data['other_specialist']) ? session()->put('other_specialist', $data['other_specialist']) : null;

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

            $feedbackOne = session()->get('feedbackOne');

            $medicationsArr         = session()->get('medications') ?? null;
            $labsArr                = session()->get('labs') ?? null;
            $otherLabsArr           = session()->get('other_labs') ?? null;
            $studiesArr             = session()->get('studies') ?? null;
            $otherStudiesArr        = session()->get('other_studies') ?? null;
            $consultSpecialistArr   = session()->get('consult_specialist') ?? null;
            $otherSpecialistArr     = session()->get('other_specialist') ?? null;

            if($feedbackOne != true)
            {
                $finalArrLabs           = array_merge($labsArr, $otherLabsArr);
                $finalArrStudies        = array_merge($studiesArr, $otherStudiesArr);
                $finalArrSpecialist     = array_merge($consultSpecialistArr, $otherSpecialistArr);
                
            }


            // dd($finalArrLabs, $finalArrStudies, $finalArrSpecialist);  

            //Arreglo de medicamento
            if(!empty($medicationsArr) && $medicationsArr[0]['medicines'] != null) {
                // Log::info('Medicamentos: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($medicationsArr); $i++) {
                    $medications = new TelemedicinePatientMedications();
                    $medications->telemedicine_consultation_patient_id  = $record['id'];
                    $medications->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                    $medications->telemedicine_case_id                  = $record['telemedicine_case_id'];
                    $medications->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                    $medications->medicine                              = $medicationsArr[$i]['medicines'];
                    $medications->indications                           = $medicationsArr[$i]['indications'];
                    $medications->save();
                }
            }

            //Arreglo de Laboratorios
            if (!empty($finalArrLabs)) {
                // Log::info('Lab: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrLabs); $i++) {
                    $labs = new TelemedicinePatientLab();
                    $labs->telemedicine_consultation_patient_id  = $record['id'];
                    $labs->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                    $labs->telemedicine_case_id                  = $record['telemedicine_case_id'];
                    $labs->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                    $labs->laboratory                            = $finalArrLabs[$i];
                    $labs->type                                  = TelemedicineListLaboratory::where('name', $finalArrLabs[$i])->first()->type;
                    $labs->assigned_by                           = Auth::user()->id;
                    $labs->save();
                }
            }

            //Arreglo de Estudios
            if (!empty($finalArrStudies)) {
                // Log::info('Estudios: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrStudies); $i++) {
                    $study = new TelemedicinePatientStudy();
                    $study->telemedicine_consultation_patient_id  = $record['id'];
                    $study->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                    $study->telemedicine_case_id                  = $record['telemedicine_case_id'];
                    $study->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                    $study->study                                 = $finalArrStudies[$i];
                    $study->assigned_by                           = Auth::user()->id;
                    $study->type                                  = TelemedicineListStudy::where('name', $finalArrStudies[$i])->first()->type;
                    $study->save();
                }
            }

            //Arreglo Especialistas
            if (!empty($finalArrSpecialist)) {
                // Log::info('Especialista: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrSpecialist); $i++) {
                    $specialist = new TelemedicinePatientSpecialty();
                    $specialist->telemedicine_consultation_patient_id  = $record['id'];
                    $specialist->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                    $specialist->telemedicine_case_id                  = $record['telemedicine_case_id'];
                    $specialist->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                    $specialist->specialty                            = $finalArrSpecialist[$i];
                    $specialist->assigned_by                           = Auth::user()->id;
                    $specialist->type                                  = TelemedicineListSpecialist::where('name', $finalArrSpecialist[$i])->first()->type;
                    $specialist->save();
                }
            }

            //...Limpio la variable de sesion
            session()->forget('medications');
            session()->forget('labs');
            session()->forget('other_labs');
            session()->forget('studies');
            session()->forget('other_studies');
            session()->forget('consult_specialist');
            session()->forget('other_specialist');

            //Actualizo el estatus del
            
            if(isset($feedbackOne) && $feedbackOne == true){
                //Actualizamos la informacion en la tabla de casos
                $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                $case->updated_at = now();
                $case->status = 'ALTA MEDICA';
                $case->save();

                //Actualizamos la informacion en la tabla de consultas
                $consult = TelemedicineConsultationPatient::where('id', $record['id'])->first();
                $consult->updated_at = now();
                $consult->status = 'ALTA MEDICA';
                $consult->save();

                session()->forget('feedbackOne');

                
            } else {
                $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                $case->updated_at = now();
                $case->status = 'EN SEGUIMIENTO';
                $case->save();
            }

            
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