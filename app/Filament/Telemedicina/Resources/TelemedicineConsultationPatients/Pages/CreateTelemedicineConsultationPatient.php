<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\TelemedicineFollowUp;
use Illuminate\Support\Facades\Auth;
use App\Models\TelemedicineListStudy;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Models\TelemedicinePatientLab;
use Filament\Forms\Components\Checkbox;
use App\Models\TelemedicinePatientStudy;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use App\Models\TelemedicinePatientSpecialty;
use Filament\Infolists\Components\TextEntry;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    /**
     * El paciente se obtiene de la sesión (como se vio en errores anteriores).
     */
    protected $patient;

    public function mount(): void
    {
        // 1. Llama al método mount original de Filament para inicializar el formulario
        parent::mount();

        // 2. Obtener el paciente desde la sesión
        $this->patient = session()->get('patient');

        if (!$this->patient) {
            // Manejar si el paciente no está en sesión (por seguridad)
            Notification::make()
                ->title('Error: Paciente no encontrado.')
                ->danger()
                ->send();

            // Redirigir a la página de selección de pacientes o al índice
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        // 3. Verificar si el paciente NO tiene historia clínica
        $hasHistory = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $this->patient->id)
            ->exists();

        // Si NO tiene historia, muestra el modal (o redirige)
        if (! $hasHistory) {
            // Lógica para mostrar el modal:
            // Opción A: Usar una notificación/alerta clara con un enlace para crear la historia.

            //Creo la variable de session que va a manejar la respuesta
            session()->put('redCode', true);

            Notification::make()
                ->title('¡Atención: Historia Clínica Pendiente! ⚠️')
                ->body('El paciente no tiene un registro de historia clínica. Debe crearlo antes de continuar con la consulta.')
                ->actions([
                    // Este es el Action que se mostrará dentro de la Notificación
                    Action::make('create_history')
                        ->label('Historia Clínica')
                        ->button()
                        ->url(
                            TelemedicineHistoryPatientResource::getUrl('create', [
                                'patientId' => $this->patient->id, // Pasa el ID del paciente
                            ])
                        )
                        ->close(),
                    Action::make('halt')
                        ->label('Clave Roja')
                        ->icon('heroicon-c-finger-print')
                        ->color('critico')
                        ->button()
                        ->dispatch('undoEditingPost')
                        ->close(),
                ])
                ->icon('heroicon-s-exclamation-triangle')
                ->iconColor('critico')
                ->color('critico') // Usa un color que llame la atención
                ->persistent() // Mantiene la notificación hasta que se cierre o actúe
                ->send();

            // Opcional: Redirigir inmediatamente a la creación de la historia para forzar el flujo.
            // Esto elimina la necesidad de que el usuario haga clic en el botón de la notificación.
            // return $this->redirect(\App\Filament\Telemedicina\Resources\TelemedicineHistoryPatientResource::getUrl('create', ['patientId' => $this->patient->id]));
        }

        // Si SÍ tiene historia clínica, el mount() continúa y carga el formulario normalmente.
    }

    /**
     * Define los eventos de Livewire que este componente debe escuchar.
     */
    protected function getListeners(): array
    {
        return [
            // 'evento' => 'metodo_a_ejecutar'
            'undoEditingPost' => 'handleUndoEditingPost',
        ];
    }

    /**
     * Método que se ejecuta cuando se dispara el evento 'undoEditingPost'.
     * @param array $params (Opcional: recibe los datos pasados por el evento)
     */
    public function handleUndoEditingPost(): void
    {
        // dd('Clave Roja recibida');  
        // 💡 Aquí va la lógica que quieres que ocurra en el Wizard.
        // Por ejemplo, resetear campos, cambiar de paso, o mostrar una notificación.

        // EJEMPLO: Resetear el formulario
        session()->put('redCode', false);

        // EJEMPLO: Mostrar una notificación
        Notification::make()
            ->title('¡Alerta de Clave Roja recibida! 🚨')
            ->body('El formulario ha recibido la señal de "Clave Roja".')
            ->icon('heroicon-c-finger-print')
            ->color('urgencia') // Usa un color que llame la atención()
            ->send();

        // EJEMPLO: Redirigir o Resetear el formulario
        // $this->form->fill(); // Esto resetearía el formulario
        // return $this->redirect('/ruta-de-seguridad');
    }

    public function getTitle(): string | Htmlable
    {

        $patient = session()->get('patient');
        return new HtmlString(
            '<div style="display: flex; flex-direction: column;">' .
                '<span style="font-weight: bold; font-size: 1rem; color: #005ca9;">' . // Tono azul oscuro similar a primary-700
                    'Nombra y Apellido: ' . $patient->full_name .
                '</span>' .
                '<span style="font-size: 1rem; color: #005ca9;">' . // Tono gris oscuro similar a gray-600
                    'Cédula: V-' . $patient->nro_identificacion .
                '</span>' .
            '</div>'
        );
    }
    

    protected function getHeaderActions(): array
    {
        return [

            Action::make('back_dashboard')
                ->label('Dashboard')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('estandar')
                ->url(route('filament.telemedicina.pages.dashboard')),

            Action::make('create_history')
                ->label('Registrar Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('urgencia')
                ->action(function () {

                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->first();

                    return redirect(TelemedicineHistoryPatientResource::getUrl('create', ['record' => $patient->id]));
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->exists();
                    return $records;
                }),

            Action::make('edit_history')
                ->label('Editar Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('urgencia')
                ->action(function () {
                    
                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->first();
                    // dd($record);

                    return redirect(TelemedicineHistoryPatientResource::getUrl('edit', ['record' => $record->id]));

                    // return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.edit', ['id' => $records->id]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->exists();
                    return !$record;
                }),
                    
                
            Action::make('view_history')
                ->label('Resumen Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('primary')
                ->modalSubmitAction(false)
                ->modalContent(function () {
                    
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->first();

                    return view('history-patient-infolist', ['record' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->exists();
                    return !$records;
                }),
                
            
            Action::make('consultation_history')
                ->label('Histórico del Caso')
                ->button()
                ->icon('heroicon-s-clipboard-document-list')
                ->color('primary')
                ->slideOver()
                ->modalHeading('Historial de Casos del Paciente')
                ->modalContent(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicineConsultationPatients()->orderByDesc('created_at')->get();
                    // dd($records);
                    return view('consultation-patient-table', ['records' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient->telemedicineConsultationPatients()->exists();
                    return !$records;
                }),
            
        ];
    }

    protected function getTelemedicineCaseTable()
    {
        
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

            $medicationsArr         = session()->get('medications') ?? [];
            $labsArr                = session()->get('labs') ?? [];
            $otherLabsArr           = session()->get('other_labs') ?? [];
            $studiesArr             = session()->get('studies') ?? [];
            $otherStudiesArr        = session()->get('other_studies') ?? [];
            $consultSpecialistArr   = session()->get('consult_specialist') ?? [];
            $otherSpecialistArr     = session()->get('other_specialist') ?? [];


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
                    $medications->duration                              = $medicationsArr[$i]['duration'];
                    $medications->telemedicine_priority_id              = $record['telemedicine_priority_id'];
                    $medications->assigned_by                           = Auth::user()->id;
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

            //...Activacion de la clave roja
            session()->forget('redCode');

            //...Limpio la variable de sesion que se generar al momento acceder al caso para la primera consulta
            session()->forget('case');
            session()->forget('patient');
            session()->forget('redCode');

            //...Limpio la variable de sesion que se crea cuando asociamos algun antecedente de la lista
            session()->forget('patologicalHistorySelected');

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