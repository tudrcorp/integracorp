<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Http\Controllers\OperationCoordinationServiceController;
use App\Http\Controllers\TelemedicineMedicalReportController;
use App\Jobs\GeneratePdfEspecialista;
use App\Jobs\GeneratePdfImagenologia;
use App\Jobs\GeneratePdfInformeMedicoCorto;
use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfLaboratorio;
use App\Jobs\GeneratePdfMedicamentos;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Services\NotificationTelemedicinaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    /**
     * El paciente se obtiene de la sesión (como se vio en errores anteriores).
     */
    protected $patient;

    protected $case;

    public function mount(): void
    {
        // 1. Llama al método mount original de Filament para inicializar el formulario
        parent::mount();

        // 2. Obtener el paciente desde la sesión
        $this->patient = session()->get('patient');
        $this->case = session()->get('case');

        if (! $this->patient) {
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

            // Creo la variable de session que va a manejar la respuesta
            session()->put('redCode', true);

            Notification::make()
                ->title('¡Atención: Historia Clínica Pendiente! ⚠️')
                ->body('El paciente no tiene un registro de historia clínica. Debe crearlo antes de continuar con la consulta. Si es un paciente crítico o de emergencia ingresa por clave roja.')
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
                        ->hidden(function () {
                            $consultation = TelemedicineConsultationPatient::where('telemedicine_case_id', $this->case->id)
                                ->where('telemedicine_case_code', $this->case->code)
                                ->where('status', 'CONSULTA INICIAL')
                                ->count();
                            if ($consultation > 0) {
                                return true;
                            }

                            return false;
                        })
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
     *
     * @param  array  $params  (Opcional: recibe los datos pasados por el evento)
     */
    public function handleUndoEditingPost(): void
    {

        // EJEMPLO: Resetear el formulario
        session()->put('redCode', false);

        // EJEMPLO: Mostrar una notificación
        Notification::make()
            ->title('¡Alerta de Clave Roja recibida! 🚨')
            ->body('El formulario ha recibido la señal de "Clave Roja".')
            ->icon('heroicon-c-finger-print')
            ->color('urgencia') // Usa un color que llame la atención()
            ->send();
    }

    public function getTitle(): string|Htmlable
    {

        $patient = session()->get('patient');

        return new HtmlString(
            '<div style="display: flex; flex-direction: column;">'.
                '<span style="font-weight: bold; font-size: 1rem; color: #005ca9;">'. // Tono azul oscuro similar a primary-700
                    'Nombra y Apellido: '.$patient->full_name.
                '</span>'.
                '<span style="font-size: 1rem; color: #005ca9;">'. // Tono gris oscuro similar a gray-600
                    'Cédula: V-'.$patient->nro_identificacion.
                '</span>'.
                '<span style="font-size: 1rem; color: #005ca9;">'. // Tono gris oscuro similar a gray-600
                    'Edad: '.$patient->age.
                '</span>'.
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

                    return ! $record;
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

                    return ! $records;
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

                    return ! $records;
                }),

            Action::make('consultation_history_case')
                ->label('Últimos Casos')
                ->button()
                ->icon('heroicon-s-clipboard-document-list')
                ->color('primary')
                ->slideOver()
                ->modalHeading('Historial de Casos del Paciente')
                ->modalContent(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicineCases()->orderByDesc('created_at')->get();

                    // dd($records);
                    return view('table-telemedicine-cases', ['records' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient->telemedicineCases()->exists();

                    return ! $records;
                }),

        ];
    }

    protected function getTelemedicineCaseTable() {}

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        if (isset($data['feedbackOne']) && $data['feedbackOne'] == true) {
            session()->put('feedbackOne', $data['feedbackOne']);
        }
        // ...Asignamos los valores a la variable de sesion
        // Medicamentos
        isset($data['medications']) ? session()->put('medications', $data['medications']) : null;

        // Laboratorios
        isset($data['labs']) ? session()->put('labs', $data['labs']) : null;
        isset($data['other_labs']) ? session()->put('other_labs', $data['other_labs']) : null;

        // Estudios
        isset($data['studies']) ? session()->put('studies', $data['studies']) : null;
        isset($data['other_studies']) ? session()->put('other_studies', $data['other_studies']) : null;

        // Consultas con especialistas
        isset($data['consult_specialist']) ? session()->put('consult_specialist', $data['consult_specialist']) : null;
        isset($data['other_specialist']) ? session()->put('other_specialist', $data['other_specialist']) : null;

        return $data;
    }

    /**
     * Creamos el registro de los medicamentos
     * asignados por el medico en la consulta
     *
     * @author TuDrEnCasa
     *
     * @since 1.0
     *
     * @version 1.0
     *
     * @param  array  $data,  array $medications
     * @return void
     */
    protected function afterCreate()
    {
        try {

            $record = $this->getRecord()->toArray();

            $doctor = TelemedicineDoctor::where('id', $record['telemedicine_doctor_id'])->first()->toArray();

            $patient = TelemedicinePatient::where('id', $record['telemedicine_patient_id'])->first()->toArray();

            // Envuelve el codigo en un try catch y una transaccion para que si hay un error se pueda revertir el cambio
            DB::transaction(function () use ($record, $doctor, $patient) {

                try {

                    // LIsta de Variables para generar los reportes
                    $dataMedicamentos = [];
                    $dataLaboratorios = [];
                    $dataEstudios = [];
                    $dataEspecialistas = [];

                    $feedbackOne = session()->get('feedbackOne');

                    $medicationsArr = session()->get('medications') ?? [];
                    $labsArr = session()->get('labs') ?? [];
                    $otherLabsArr = session()->get('other_labs') ?? [];
                    $studiesArr = session()->get('studies') ?? [];
                    $otherStudiesArr = session()->get('other_studies') ?? [];
                    $consultSpecialistArr = session()->get('consult_specialist') ?? [];
                    $otherSpecialistArr = session()->get('other_specialist') ?? [];

                    if ($feedbackOne != true) {
                        $finalArrLabs = array_merge($labsArr, $otherLabsArr);
                        $finalArrStudies = array_merge($studiesArr, $otherStudiesArr);
                        $finalArrSpecialist = array_merge($consultSpecialistArr, $otherSpecialistArr);
                    }

                    // dd($finalArrLabs, $finalArrStudies, $finalArrSpecialist);

                    // Arreglo de medicamento
                    if (! empty($medicationsArr) && $medicationsArr[0]['medicines'] != null) {

                        for ($i = 0; $i < count($medicationsArr); $i++) {
                            $medications = new TelemedicinePatientMedications;
                            $medications->telemedicine_consultation_patient_id = $record['id'];
                            $medications->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $medications->telemedicine_case_id = $record['telemedicine_case_id'];
                            $medications->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $medications->medicine = $medicationsArr[$i]['medicines'];
                            $medications->indications = $medicationsArr[$i]['indications'];
                            $medications->duration = $medicationsArr[$i]['duration'];
                            $medications->telemedicine_priority_id = $record['telemedicine_priority_id'];
                            $medications->assigned_by = Auth::user()->id;
                            $medications->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'medicamentos';

                        $dataMedicamentos = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'medicationsArr' => $medicationsArr,
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        // GeneratePdfMedicamentos::dispatch($dataMedicamentos, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // Arreglo de Laboratorios
                    if (! empty($finalArrLabs)) {
                        // Log::info('Lab: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrLabs); $i++) {
                            $labs = new TelemedicinePatientLab;
                            $labs->telemedicine_consultation_patient_id = $record['id'];
                            $labs->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $labs->telemedicine_case_id = $record['telemedicine_case_id'];
                            $labs->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $labs->laboratory = $finalArrLabs[$i];
                            $labs->type = TelemedicineListLaboratory::where('name', $finalArrLabs[$i])->first()->type;
                            $labs->assigned_by = Auth::user()->id;
                            $labs->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'laboratorios';

                        $dataLaboratorios = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'labs' => $record['labs'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        // GeneratePdfLaboratorio::dispatch($dataLaboratorios, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // Arreglo de Estudios
                    if (! empty($finalArrStudies)) {
                        // Log::info('Estudios: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrStudies); $i++) {
                            $study = new TelemedicinePatientStudy;
                            $study->telemedicine_consultation_patient_id = $record['id'];
                            $study->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $study->telemedicine_case_id = $record['telemedicine_case_id'];
                            $study->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $study->study = $finalArrStudies[$i];
                            $study->assigned_by = Auth::user()->id;
                            $study->type = TelemedicineListStudy::where('name', $finalArrStudies[$i])->first()->type;
                            $study->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'imagenologia';

                        $dataEstudios = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'studies' => $record['studies'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'phone' => $patient['phone'],
                            'signature' => $doctor['signature'],
                        ];

                        // Bus::chain([

                        //     new GeneratePdfImagenologia($data, Auth::user(), $typeDoc),

                        //     new SendTelemedicinaDocument($data['telemedicine_patient_id'], $data['telemedicine_case_id'], Auth::user(), $patient['phone'], $typeDoc),

                        // ])->onQueue('telemedicina')->dispatch();

                        // GeneratePdfImagenologia::dispatch($dataEstudios, Auth::user(), $typeDoc)->onQueue('telemedicina');

                    }

                    // Arreglo Especialistas
                    if (! empty($finalArrSpecialist)) {
                        // Log::info('Especialista: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrSpecialist); $i++) {
                            $specialist = new TelemedicinePatientSpecialty;
                            $specialist->telemedicine_consultation_patient_id = $record['id'];
                            $specialist->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $specialist->telemedicine_case_id = $record['telemedicine_case_id'];
                            $specialist->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $specialist->specialty = $finalArrSpecialist[$i];
                            $specialist->assigned_by = Auth::user()->id;
                            $specialist->type = TelemedicineListSpecialist::where('name', $finalArrSpecialist[$i])->first()->type;
                            $specialist->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'especialista';

                        $dataEspecialistas = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];
                        // dd($data);

                        // GeneratePdfEspecialista::dispatch($dataEspecialistas, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // ...Limpio la variable de sesion
                    session()->forget('medications');
                    session()->forget('labs');
                    session()->forget('other_labs');
                    session()->forget('studies');
                    session()->forget('other_studies');
                    session()->forget('consult_specialist');
                    session()->forget('other_specialist');

                    // ...Activacion de la clave roja
                    session()->forget('redCode');

                    // ...Limpio la variable de sesion que se generar al momento acceder al caso para la primera consulta
                    session()->forget('case');
                    session()->forget('patient');
                    session()->forget('redCode');

                    // ...Limpio la variable de sesion que se crea cuando asociamos algun antecedente de la lista
                    session()->forget('patologicalHistorySelected');

                    // Actualizo el estatus del

                    if (isset($feedbackOne) && $feedbackOne == true) {
                        // Actualizamos la informacion en la tabla de casos
                        $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                        $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                        $case->updated_at = now();
                        $case->status = 'ALTA MEDICA';
                        $case->save();

                        // Actualizamos la informacion en la tabla de consultas
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

                    // Notificion al usuario de que los documentos estan siendo generados y qye luego los recibira via WP
                    $this->sendNotifications($record);

                    if ($record['status'] == 'CONSULTA INICIAL') {
                        $dataInformeCorteo = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $this->data['code_reference'],
                            'name_patient' => $this->data['full_name'],
                            'ci_patient' => $this->data['nro_identificacion'],
                            'age_patient' => $this->data['age'],
                            'reason' => $this->data['reason_consultation'],
                            'actual_phatology' => $this->data['actual_phatology'],
                            'background' => $this->data['background'],
                            'diagnostic_impression' => $this->data['diagnostic_impression'],
                            'peso' => $this->data['peso'],
                            'estatura' => $this->data['estatura'],
                            'imc' => $this->data['imc'],
                            'phone' => $this->data['phone_ppal'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'medicationsArr' => $medicationsArr ?? [],
                            'labsArr' => $labsArr ?? [],
                            'otherLabsArr' => $otherLabsArr ?? [],
                            'studiesArr' => $studiesArr ?? [],
                            'otherStudiesArr' => $otherStudiesArr ?? [],
                            'consultSpecialistArr' => $consultSpecialistArr ?? [],
                            'otherSpecialistArr' => $otherSpecialistArr ?? [],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        GeneratePdfInformeMedicoCorto::dispatch($dataInformeCorteo, Auth::user(), 'informe-corto')->onQueue('telemedicina');

                        $dataInformeLargo = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $this->data['code_reference'],
                            'name_patient' => $this->data['full_name'],
                            'ci_patient' => $this->data['nro_identificacion'],
                            'age_patient' => $this->data['age'],
                            'reason' => $this->data['reason_consultation'],
                            'actual_phatology' => $this->data['actual_phatology'],
                            'background' => $this->data['background'],
                            'diagnostic_impression' => $this->data['diagnostic_impression'],
                            'peso' => $this->data['peso'],
                            'estatura' => $this->data['estatura'],
                            'imc' => $this->data['imc'],
                            'phone' => $this->data['phone_ppal'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'medicationsArr' => $medicationsArr ?? [],
                            'labsArr' => $labsArr ?? [],
                            'otherLabsArr' => $otherLabsArr ?? [],
                            'studiesArr' => $studiesArr ?? [],
                            'otherStudiesArr' => $otherStudiesArr ?? [],
                            'consultSpecialistArr' => $consultSpecialistArr ?? [],
                            'otherSpecialistArr' => $otherSpecialistArr ?? [],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                            'pa' => $this->data['pa'],
                            'fc' => $this->data['fc'],
                            'fr' => $this->data['fr'],
                            'temp' => $this->data['temp'],
                            'saturacion' => $this->data['saturacion'],
                        ];

                        GeneratePdfInformeMedicoLargo::dispatch($dataInformeLargo, Auth::user(), 'informe-largo')->onQueue('telemedicina');
                    }

                    /**
                     * Ejecucion de Jobs para crear los documentos PDF
                     * ----------------------------------------------------------------------------------------------------
                     *
                     * $dataMedicamentos type array
                     * $dataLaboratorios type array
                     * $dataEstudios type array
                     * $dataEspecialistas type array
                     */
                    if ($dataMedicamentos != []) {

                        GeneratePdfMedicamentos::dispatch($dataMedicamentos, Auth::user(), 'medicamentos')->onQueue('telemedicina');

                        // Genero el servicio de coordinacion
                        $registeredOperationCoordinationService = OperationCoordinationServiceController::create($record, $doctor, $patient);

                    }

                    if ($dataLaboratorios != []) {

                        GeneratePdfLaboratorio::dispatch($dataLaboratorios, Auth::user(), 'laboratorios')->onQueue('telemedicina');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::create($record, $doctor, $patient);

                    }

                    if ($dataEstudios != []) {

                        GeneratePdfImagenologia::dispatch($dataEstudios, Auth::user(), 'imagenologia')->onQueue('telemedicina');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::create($record, $doctor, $patient);

                    }

                    if ($dataEspecialistas != []) {

                        GeneratePdfEspecialista::dispatch($dataEspecialistas, Auth::user(), 'especialista')->onQueue('telemedicina');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::create($record, $doctor, $patient);

                    }

                    /**
                     * Creacion del informe medico
                     * ----------------------------------------------------------------------------------------------------
                     *
                     * @record = Informacion de la consulta
                     *
                     * * @version 1.0
                     */
                    TelemedicineMedicalReportController::create($record);

                    Notification::make()
                        ->title('Telemedicina creada exitosamente')
                        ->success()
                        ->send();

                } catch (\Throwable $th) {
                    Log::error('Error al crear la telemedicina: '.$th->getMessage());
                    Notification::make()
                        ->title('Error al crear la telemedicina')
                        ->body($th->getMessage())
                        ->danger()
                        ->send();
                    throw $th;
                }
            });

            $registeredOperationCoordinationService = OperationCoordinationService::where('telemedicine_consultation_patient_id', $record['id'])->first()->id;

            // actualiza la tabla de medicamentoa con el id del servicio de coordinacion
            $medications = TelemedicinePatientMedications::where('telemedicine_consultation_patient_id', $record['id'])->get();
            foreach ($medications as $medication) {
                $medication->operation_coordination_service_id = $registeredOperationCoordinationService;
                $medication->save();
            }

            // actualiza la tabla de laboratorios con el id del servicio de coordinacion
            $labs = TelemedicinePatientLab::where('telemedicine_consultation_patient_id', $record['id'])->get();
            foreach ($labs as $lab) {
                $lab->operation_coordination_service_id = $registeredOperationCoordinationService;
                $lab->save();
            }

            // actualiza la tabla de estudios con el id del servicio de coordinacion
            $studies = TelemedicinePatientStudy::where('telemedicine_consultation_patient_id', $record['id'])->get();
            foreach ($studies as $study) {
                $study->operation_coordination_service_id = $registeredOperationCoordinationService;
                $study->save();
            }

            // actualiza la tabla de especialistas con el id del servicio de coordinacion
            $specialists = TelemedicinePatientSpecialty::where('telemedicine_consultation_patient_id', $record['id'])->get();
            foreach ($specialists as $specialist) {
                $specialist->operation_coordination_service_id = $registeredOperationCoordinationService;
                $specialist->save();
            }

        } catch (\Throwable $th) {
            Log::error('Error en afterCreate (telemedicina): '.$th->getMessage(), [
                'exception' => $th,
            ]);
            Notification::make()
                ->title('Error al crear la consulta de telemedicina')
                ->body($th->getMessage())
                ->danger()
                ->send();
            throw $th;
        }

    }

    private function sendNotifications()
    {
        $record = $this->getRecord()->toArray();

        $patient = TelemedicinePatient::where('id', $record['telemedicine_patient_id'])->first()->toArray();

        $masiveNotification = new NotificationTelemedicinaService;
        $masiveNotification->sendPreviewNotification($patient['phone']);
    }

    public function getRedirectUrl(): string
    {
        // redirect to dashboard
        return URL::route('filament.telemedicina.pages.dashboard');
    }
}
